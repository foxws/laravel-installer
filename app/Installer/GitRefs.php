<?php

declare(strict_types=1);

namespace App\Installer;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * The branches and tags of a repository, by name, with the commit each one
 * points to, as "git ls-remote" lists them.
 */
readonly class GitRefs
{
    /**
     * @param  array<string, string>  $branches
     * @param  array<string, string>  $tags
     */
    public function __construct(
        public array $branches,
        public array $tags,
        public ?string $defaultBranch = null,
    ) {}

    /**
     * Annotated tags are listed twice: as the tag object, and peeled ("^{}")
     * as the commit it points to, which is the one to show. The default
     * branch comes first, and the tags start with the newest version.
     */
    public static function fromLsRemote(string $output): self
    {
        $branches = [];
        $tags = [];
        $defaultBranch = null;

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (preg_match('#^ref: refs/heads/(\S+)\s+HEAD$#', $line, $match)) {
                $defaultBranch = $match[1];

                continue;
            }

            if (! preg_match('#^([0-9a-f]{40})\s+refs/(heads|tags)/(.+)$#', $line, $match)) {
                continue;
            }

            [, $sha, $kind, $name] = $match;

            if ($kind === 'heads') {
                $branches[$name] = $sha;

                continue;
            }

            if (Str::endsWith($name, '^{}')) {
                $tags[Str::beforeLast($name, '^{}')] = $sha;

                continue;
            }

            $tags[$name] ??= $sha;
        }

        ksort($branches, SORT_NATURAL);

        if ($defaultBranch !== null && isset($branches[$defaultBranch])) {
            $branches = [$defaultBranch => $branches[$defaultBranch], ...$branches];
        }

        return new self($branches, static::sortTags($tags), $defaultBranch);
    }

    /**
     * Versions first, newest on top, then the other tags.
     *
     * @param  array<string, string>  $tags
     * @return array<string, string>
     */
    protected static function sortTags(array $tags): array
    {
        $parser = new VersionParser;

        $versions = array_filter(array_keys($tags), function (string $tag) use ($parser): bool {
            try {
                $parser->normalize($tag);

                return true;
            } catch (UnexpectedValueException) {
                return false;
            }
        });

        $others = array_diff(array_keys($tags), $versions);
        rsort($others, SORT_NATURAL);

        return collect([...Semver::rsort($versions), ...$others])
            ->mapWithKeys(fn (string $tag): array => [$tag => $tags[$tag]])
            ->all();
    }

    /**
     * The commit a branch or tag points to.
     */
    public function sha(RefType $type, string $name): ?string
    {
        return match ($type) {
            RefType::Branch => $this->branches[$name] ?? null,
            RefType::Tag => $this->tags[$name] ?? null,
            RefType::Commit => preg_match('/^[0-9a-f]{40}$/', $name) ? $name : null,
        };
    }
}
