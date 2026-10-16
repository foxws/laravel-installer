<?php

declare(strict_types=1);

namespace App\Installer\Concerns;

use App\Installer\ServicesException;
use Closure;
use Foxws\Podman\Support\PodmanConfig;
use Foxws\Podman\Support\PodmanPresetGenerator;
use Foxws\Podman\Support\PodmanQuadletPath;
use Foxws\Podman\Support\PodmanRenderedPreset;
use Illuminate\Support\Facades\Config;

/**
 * Renders the workspace's presets with foxws/laravel-podman. The app's own
 * ".env" values decide what's rendered, without becoming the installer's own
 * config.
 */
trait InteractsWithPodman
{
    /**
     * The app's quadlet prefix, such as "my-app", which names its units and
     * containers ("systemd-my-app").
     */
    public function application(): string
    {
        return $this->usingPodmanConfig(fn (): string => (new PodmanConfig)->prefix());
    }

    /**
     * What a services step will install, to show before it does.
     *
     * @param  list<string>  $presets
     * @return array{name: string, url: string, path: string, presets: list<string>}
     */
    public function describe(array $presets = []): array
    {
        return $this->usingPodmanConfig(fn (): array => [
            'name' => Config::string('app.name'),
            'url' => Config::string('app.url'),
            'path' => (new PodmanQuadletPath)->workingPath(),
            'presets' => $presets ?: (new PodmanConfig)->defaultPresets(),
        ]);
    }

    /**
     * Render the given presets, or the app's default ones, into the
     * workspace.
     *
     * @param  list<string>  $presets
     * @return list<PodmanRenderedPreset>
     */
    public function render(array $presets = []): array
    {
        return $this->usingPodmanConfig(function () use ($presets): array {
            $presets = $presets ?: (new PodmanConfig)->defaultPresets();

            if ($presets === []) {
                throw ServicesException::noPresets();
            }

            $generator = new PodmanPresetGenerator;

            return array_map(fn (string $preset): PodmanRenderedPreset => $generator->generate($preset), $presets);
        });
    }

    /**
     * Run the callback with the app's "app.*" and "podman.*" config, as its
     * own ".env" file would set them, then restore the installer's own.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function usingPodmanConfig(Closure $callback): mixed
    {
        if (! $this->isAvailable()) {
            throw ServicesException::noWorkspace();
        }

        $original = ['app' => Config::array('app'), 'podman' => Config::array('podman')];
        $server = $_SERVER;
        $env = $_ENV;

        $values = $this->environment();

        try {
            // The installer's own "PODMAN_*" environment comes from "lpod
            // installer" and describes the host, so it wins. Its "APP_*"
            // values describe the installer itself, so the app's win...
            foreach ($values as $name => $value) {
                if (str_starts_with($name, 'PODMAN_') && array_key_exists($name, $server)) {
                    continue;
                }

                $_SERVER[$name] = $_ENV[$name] = $value;
            }

            Config::set('app.name', $values['APP_NAME'] ?? 'Laravel');
            Config::set('app.url', $values['APP_URL'] ?? 'http://localhost');
            Config::set('app.env', $values['APP_ENV'] ?? 'production');
            Config::set('podman', require $this->podmanConfigFile());
            Config::set('podman.base_path', $this->path);

            return $callback();
        } finally {
            $_SERVER = $server;
            $_ENV = $env;

            Config::set('app', $original['app']);
            Config::set('podman', $original['podman']);
        }
    }

    /**
     * The app's own "config/podman.php" when it has one, so its custom
     * substitutions and settings apply, or else the package's.
     */
    protected function podmanConfigFile(): string
    {
        $appConfig = "{$this->path}/config/podman.php";

        if (is_file($appConfig)) {
            return $appConfig;
        }

        return (new PodmanQuadletPath)->vendorPath().'/config/podman.php';
    }
}
