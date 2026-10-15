<?php

namespace App\Installer;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

/**
 * Runs commands in the app's containers with "podman exec", through the
 * Podman socket mounted into the installer.
 */
class Podman
{
    /**
     * Environment values are handed to "podman exec" by name only ("-e NAME"),
     * so Podman reads them from its own environment and they never show up
     * in its arguments.
     *
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    public function exec(string $container, array $command, ?string $stdin = null, array $env = []): ProcessResult
    {
        $arguments = [config('installer.podman'), 'exec'];

        if ($stdin !== null) {
            $arguments[] = '--interactive';
        }

        foreach (array_keys($env) as $name) {
            array_push($arguments, '--env', $name);
        }

        $process = Process::timeout(config('installer.command_timeout'))->env($env);

        if ($stdin !== null) {
            $process = $process->input($stdin);
        }

        return $process->run([...$arguments, $container, ...$command]);
    }
}
