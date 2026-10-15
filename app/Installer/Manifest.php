<?php

namespace App\Installer;

use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The app's installer: its name and the steps to go through, read from the
 * "manifest.yml" in the installer folder. Each step points to a Markdown file
 * next to it, whose front matter can set the step's title.
 */
readonly class Manifest
{
    /**
     * @param  list<Step>  $steps
     */
    public function __construct(
        public string $name,
        public array $steps,
    ) {}

    public static function fromPath(string $path): self
    {
        $file = "{$path}/manifest.yml";

        if (! is_file($file)) {
            throw InvalidManifestException::missing($file);
        }

        try {
            $manifest = Yaml::parseFile($file);
        } catch (ParseException $exception) {
            throw InvalidManifestException::invalid($file, $exception->getMessage());
        }

        if (! is_array($manifest) || ! is_string($manifest['name'] ?? null) || ! is_array($manifest['steps'] ?? null) || $manifest['steps'] === []) {
            throw InvalidManifestException::invalid($file, 'it needs a "name" and a list of "steps".');
        }

        $converter = static::markdownConverter();
        $steps = [];

        foreach ($manifest['steps'] as $index => $step) {
            $steps[] = static::step($path, $file, $index, $step, $converter);
        }

        $ids = array_map(fn (Step $step): string => $step->id, $steps);

        if ($duplicates = array_unique(array_diff_assoc($ids, array_unique($ids)))) {
            throw InvalidManifestException::invalid($file, 'the step ids '.implode(', ', $duplicates).' are used more than once.');
        }

        return new self($manifest['name'], $steps);
    }

    /**
     * @param  array{id?: mixed, type?: mixed, file?: mixed}|mixed  $step
     */
    protected static function step(string $path, string $file, int|string $index, mixed $step, MarkdownConverter $converter): Step
    {
        if (! is_array($step) || ! is_string($step['id'] ?? null) || ! is_string($step['file'] ?? null)) {
            throw InvalidManifestException::invalid($file, "step {$index} needs an \"id\" and a \"file\".");
        }

        $type = StepType::tryFrom($step['type'] ?? StepType::Content->value)
            ?? throw InvalidManifestException::invalid($file, "step \"{$step['id']}\" has an unknown type.");

        $markdownFile = "{$path}/{$step['file']}";

        if (! is_file($markdownFile)) {
            throw InvalidManifestException::invalid($file, "step \"{$step['id']}\" points to {$step['file']}, which doesn't exist.");
        }

        $rendered = $converter->convert((string) file_get_contents($markdownFile));

        $frontMatter = $rendered instanceof RenderedContentWithFrontMatter ? $rendered->getFrontMatter() : [];

        return new Step(
            id: $step['id'],
            type: $type,
            title: is_string($frontMatter['title'] ?? null) ? $frontMatter['title'] : Str::headline($step['id']),
            html: $rendered->getContent(),
        );
    }

    /**
     * The steps come from the app's own image, but raw HTML is stripped
     * anyway, since the installer runs with access to the Podman socket.
     */
    protected static function markdownConverter(): MarkdownConverter
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FrontMatterExtension);

        return new MarkdownConverter($environment);
    }
}
