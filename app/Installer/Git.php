<?php

declare(strict_types=1);

namespace App\Installer;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Lists a repository's branches and tags, and checks one of them out into
 * the workspace, with the "git" binary.
 *
 * Only https:// repositories are fetched, without submodules or prompts. A
 * token for a private repository is handed to git through its environment
 * ("GIT_CONFIG_*"), so it never shows up in the arguments, the remote's
 * address or ".git/config".
 */
class Git
{
    public static function isValidUrl(string $url): bool
    {
        return (bool) preg_match('#^https://[^\s/@]+/\S+$#i', $url);
    }

    public function refs(string $url, ?string $token = null): GitRefs
    {
        // "--heads" and "--tags" would leave out HEAD, which says which
        // branch is the default one, so the refs are matched instead...
        $result = $this->run(['ls-remote', '--symref', '--', $url, 'HEAD', 'refs/heads/*', 'refs/tags/*'], $url, $token);

        if (! $result->successful()) {
            throw SourceException::git("read {$url}", $this->redact($result->errorOutput(), $token));
        }

        return GitRefs::fromLsRemote($result->output());
    }

    /**
     * Fetch only the given branch, tag or commit, and check it out, so the
     * workspace holds exactly that version. Returns the commit it checked
     * out.
     */
    public function checkout(string $url, string $ref, string $path, ?string $token = null): string
    {
        if (! is_dir("{$path}/.git")) {
            $this->runOrFail('create the repository', ['-C', $path, 'init', '--quiet'], $url, $token);
            $this->runOrFail('add the remote', ['-C', $path, 'remote', 'add', 'origin', '--', $url], $url, $token);
        }

        $this->runOrFail("fetch {$ref}", ['-C', $path, 'fetch', '--depth', '1', '--no-tags', '--no-recurse-submodules', 'origin', '--', $ref], $url, $token);
        $this->runOrFail("check out {$ref}", ['-C', $path, 'checkout', '--quiet', '--detach', 'FETCH_HEAD'], $url, $token);

        return trim($this->runOrFail('read the commit', ['-C', $path, 'rev-parse', 'HEAD'], $url, $token)->output());
    }

    /**
     * The address of the "origin" remote of a checkout.
     */
    public function origin(string $path): ?string
    {
        $result = $this->run(['-C', $path, 'remote', 'get-url', 'origin']);

        return $result->successful() ? trim($result->output()) : null;
    }

    /**
     * @param  list<string>  $arguments
     */
    protected function runOrFail(string $action, array $arguments, string $url, ?string $token): ProcessResult
    {
        $result = $this->run($arguments, $url, $token);

        if (! $result->successful()) {
            throw SourceException::git($action, $this->redact($result->errorOutput(), $token));
        }

        return $result;
    }

    /**
     * @param  list<string>  $arguments
     */
    protected function run(array $arguments, ?string $url = null, ?string $token = null): ProcessResult
    {
        if ($url !== null && ! static::isValidUrl($url)) {
            throw SourceException::invalidUrl();
        }

        return Process::timeout(Config::integer('installer.git_timeout'))
            ->env($this->environment($token))
            ->run([
                Config::string('installer.git'),
                '-c', 'protocol.allow=never',
                '-c', 'protocol.https.allow=always',
                '-c', 'safe.directory=*',
                ...$arguments,
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function environment(?string $token): array
    {
        $environment = [
            'GIT_TERMINAL_PROMPT' => '0',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
        ];

        if ($token === null || $token === '') {
            return $environment;
        }

        return [
            ...$environment,
            'GIT_CONFIG_COUNT' => '1',
            'GIT_CONFIG_KEY_0' => 'http.extraHeader',
            'GIT_CONFIG_VALUE_0' => 'Authorization: Basic '.base64_encode("x-access-token:{$token}"),
        ];
    }

    protected function redact(string $output, ?string $token): string
    {
        if ($token === null || $token === '') {
            return $output;
        }

        return Str::replace([$token, base64_encode("x-access-token:{$token}")], '••••', $output);
    }
}
