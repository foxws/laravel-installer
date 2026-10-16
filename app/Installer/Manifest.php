<?php

declare(strict_types=1);

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

        $inputs = static::inputs($file, $step);

        return new Step(
            id: $step['id'],
            type: $type,
            title: is_string($frontMatter['title'] ?? null) ? $frontMatter['title'] : Str::headline($step['id']),
            html: $rendered->getContent(),
            inputs: $inputs,
            run: $type === StepType::Command ? static::run($file, $step, $inputs) : null,
            presets: in_array($type, [StepType::Services, StepType::Secrets], true) ? static::presets($file, $step) : [],
            secretValues: $type === StepType::Secrets ? static::stringMap($file, $step, 'values') : [],
            secretFiles: $type === StepType::Secrets ? static::stringMap($file, $step, 'files') : [],
        );
    }

    /**
     * @param  array<string, mixed>  $step
     * @return list<Input>
     */
    protected static function inputs(string $file, array $step): array
    {
        $inputs = [];

        foreach ($step['inputs'] ?? [] as $index => $input) {
            if (! is_array($input) || ! is_string($input['name'] ?? null) || ! preg_match('/^[a-z][a-z0-9_]*$/', $input['name'])) {
                throw InvalidManifestException::invalid($file, "input {$index} of step \"{$step['id']}\" needs a \"name\" in lower snake case.");
            }

            if ($input['name'] === 'application') {
                throw InvalidManifestException::invalid($file, "input {$index} of step \"{$step['id']}\" can't be named \"application\", since {application} is the app's quadlet prefix.");
            }

            $type = InputType::tryFrom($input['type'] ?? InputType::Text->value)
                ?? throw InvalidManifestException::invalid($file, "input \"{$input['name']}\" of step \"{$step['id']}\" has an unknown type.");

            $inputs[] = new Input(
                name: $input['name'],
                label: is_string($input['label'] ?? null) ? $input['label'] : Str::headline($input['name']),
                type: $type,
                rules: is_string($input['rules'] ?? null) ? $input['rules'] : 'required',
                secret: ($input['secret'] ?? false) === true || $type === InputType::Password,
            );
        }

        return $inputs;
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  list<Input>  $inputs
     */
    protected static function run(string $file, array $step, array $inputs): CommandRun
    {
        $run = $step['run'] ?? null;

        if (! is_array($run) || ! is_string($run['container'] ?? null) || ! static::isArgumentList($run['command'] ?? null)) {
            throw InvalidManifestException::invalid($file, "step \"{$step['id']}\" needs a \"run\" with a \"container\" and a \"command\" as a list of arguments.");
        }

        if (isset($run['skip_if']) && ! static::isArgumentList($run['skip_if'])) {
            throw InvalidManifestException::invalid($file, "the \"skip_if\" of step \"{$step['id']}\" must be a list of arguments.");
        }

        $env = $run['env'] ?? [];

        if (! is_array($env) || array_filter(array_keys($env), fn (mixed $name): bool => ! is_string($name) || ! preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) || ! static::isArgumentList(array_values($env), allowEmpty: true)) {
            throw InvalidManifestException::invalid($file, "the \"env\" of step \"{$step['id']}\" must map upper case names to values.");
        }

        $commandRun = new CommandRun(
            container: $run['container'],
            command: $run['command'],
            stdin: is_string($run['stdin'] ?? null) ? $run['stdin'] : null,
            env: $env,
            skipIf: $run['skip_if'] ?? null,
        );

        // Arguments show up in "ps" and "podman inspect", so secrets may only
        // be passed through stdin or the environment...
        foreach ($inputs as $input) {
            if ($input->secret && $commandRun->mentionsInArguments($input->name)) {
                throw InvalidManifestException::invalid($file, "the secret input \"{$input->name}\" of step \"{$step['id']}\" can only be passed through \"stdin\" or \"env\".");
            }
        }

        return $commandRun;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return list<string>
     */
    protected static function presets(string $file, array $step): array
    {
        $presets = $step['presets'] ?? [];

        if (! static::isArgumentList($presets, allowEmpty: true)) {
            throw InvalidManifestException::invalid($file, "the \"presets\" of step \"{$step['id']}\" must be a list of preset names.");
        }

        return $presets;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, string>
     */
    protected static function stringMap(string $file, array $step, string $key): array
    {
        $map = $step[$key] ?? [];

        if (! is_array($map) || array_filter($map, fn (mixed $value, mixed $name): bool => ! is_string($name) || ! is_string($value), ARRAY_FILTER_USE_BOTH) !== []) {
            throw InvalidManifestException::invalid($file, "the \"{$key}\" of step \"{$step['id']}\" must map secret targets to names.");
        }

        return $map;
    }

    protected static function isArgumentList(mixed $value, bool $allowEmpty = false): bool
    {
        return is_array($value)
            && array_is_list($value)
            && ($allowEmpty || $value !== [])
            && array_filter($value, fn (mixed $argument): bool => ! is_string($argument)) === [];
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
