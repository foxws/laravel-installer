<?php

namespace App\Installer;

/**
 * The command a step runs in the app's container, such as an Artisan command
 * or a seeder. "{name}" in the arguments, stdin and environment is replaced
 * with the value of the step's input with that name.
 */
readonly class CommandRun
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     * @param  list<string>|null  $skipIf  A command that succeeds when the step was already done
     */
    public function __construct(
        public string $container,
        public array $command,
        public ?string $stdin = null,
        public array $env = [],
        public ?array $skipIf = null,
    ) {}

    public function mentionsInArguments(string $input): bool
    {
        $placeholder = "{{$input}}";

        foreach ([$this->container, ...$this->command, ...($this->skipIf ?? [])] as $argument) {
            if (str_contains($argument, $placeholder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $values
     * @return list<string>
     */
    public static function fill(array $arguments, array $values): array
    {
        return array_map(fn (string $argument): string => static::fillString($argument, $values), $arguments);
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function fillString(string $value, array $values): string
    {
        return preg_replace_callback(
            '/\{([a-z][a-z0-9_]*)\}/',
            fn (array $match): string => $values[$match[1]] ?? $match[0],
            $value,
        );
    }
}
