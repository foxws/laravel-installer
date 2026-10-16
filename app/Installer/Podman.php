<?php

declare(strict_types=1);

namespace App\Installer;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;

/**
 * Runs commands in the app's containers with "podman exec", through the
 * Podman socket mounted into the installer.
 */
class Podman
{
    /**
     * What "podman info" says about the host, or null when the socket can't
     * be reached.
     *
     * @return array<string, mixed>|null
     */
    public function info(): ?array
    {
        $result = Process::timeout(30)->run([Config::string('installer.podman'), 'info', '--format', 'json']);

        if (! $result->successful()) {
            return null;
        }

        $info = json_decode($result->output(), true);

        return is_array($info) ? $info : null;
    }

    /**
     * Connect to an on-demand socket on the host, like a visitor would, so it
     * starts the app. A short-lived container on the host's network does it,
     * since the installer has its own. It runs the installer's own image,
     * which has curl, and waits in the background until the app answers.
     */
    public function wake(string $address): ProcessResult
    {
        return Process::timeout(60)->run([
            Config::string('installer.podman'), 'run', '--rm', '--detach', '--network=host',
            '--entrypoint', 'curl', $this->ownImage(),
            '--silent', '--output', '/dev/null', '--max-time', '900', "http://{$address}/up",
        ]);
    }

    /**
     * The image the installer runs from, so helpers don't need another one.
     */
    public function ownImage(): string
    {
        $image = Config::get('installer.image');

        if (is_string($image) && $image !== '') {
            return $image;
        }

        $result = Process::timeout(30)->run([Config::string('installer.podman'), 'container', 'inspect', '--format', '{{.ImageName}}', (string) gethostname()]);

        return $result->successful() && trim($result->output()) !== '' ? trim($result->output()) : 'ghcr.io/foxws/laravel-installer:edge';
    }

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
        $arguments = [Config::string('installer.podman'), 'exec'];

        if ($stdin !== null) {
            $arguments[] = '--interactive';
        }

        foreach (array_keys($env) as $name) {
            array_push($arguments, '--env', $name);
        }

        $process = Process::timeout(Config::integer('installer.command_timeout'))->env($env);

        if ($stdin !== null) {
            $process = $process->input($stdin);
        }

        return $process->run([...$arguments, $container, ...$command]);
    }
}
