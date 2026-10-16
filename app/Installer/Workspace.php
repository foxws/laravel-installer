<?php

namespace App\Installer;

use Closure;
use Dotenv\Dotenv;
use Foxws\Podman\Support\PodmanConfig;
use Foxws\Podman\Support\PodmanPresetGenerator;
use Foxws\Podman\Support\PodmanQuadletPath;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * The app's folder on the host, mounted into the installer, which its
 * presets are rendered into with foxws/laravel-podman. The app's own ".env"
 * values decide what's rendered, without becoming the installer's own config.
 */
class Workspace
{
    public function __construct(
        public readonly ?string $path,
        public readonly ?string $envFile,
    ) {}

    public static function fromConfig(): self
    {
        $path = config('installer.workspace');
        $path = is_string($path) && $path !== '' ? Str::rtrim($path, '/') : null;

        $envFile = config('installer.env_file');
        $envFile = is_string($envFile) && $envFile !== '' ? $envFile : ($path !== null ? "{$path}/.env" : null);

        return new self($path, $envFile);
    }

    public function isAvailable(): bool
    {
        return $this->path !== null && is_dir($this->path);
    }

    /**
     * The values read from the app's ".env" file: only "APP_NAME", "APP_URL",
     * "APP_ENV" and the "PODMAN_*" ones, so secrets such as "APP_KEY" are
     * never loaded.
     *
     * @return array<string, string>
     */
    public function environment(): array
    {
        if ($this->envFile === null || ! is_file($this->envFile)) {
            return [];
        }

        $values = Dotenv::parse((string) file_get_contents($this->envFile));

        return array_filter(
            Arr::where($values, fn (?string $value, string $name): bool => in_array($name, ['APP_NAME', 'APP_URL', 'APP_ENV'], true) || str_starts_with($name, 'PODMAN_')),
            fn (?string $value): bool => $value !== null,
        );
    }

    /**
     * What a services step will install, to show before it does.
     *
     * @param  list<string>  $presets
     * @return array{name: string, url: string, path: string, presets: list<string>}
     */
    public function describe(array $presets = []): array
    {
        return $this->using(fn (): array => [
            'name' => Config::string('app.name'),
            'url' => Config::string('app.url'),
            'path' => (new PodmanQuadletPath)->workingPath(),
            'presets' => $presets ?: (new PodmanConfig)->defaultPresets(),
        ]);
    }

    /**
     * Render the given presets, or the app's default ones, into the
     * workspace. Returns the folders they were rendered to.
     *
     * @param  list<string>  $presets
     * @return list<string>
     */
    public function render(array $presets = []): array
    {
        return $this->using(function () use ($presets): array {
            $presets = $presets ?: (new PodmanConfig)->defaultPresets();

            if ($presets === []) {
                throw ServicesException::noPresets();
            }

            $generator = new PodmanPresetGenerator;

            return array_map(fn (string $preset): string => $generator->generate($preset), $presets);
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
    public function using(Closure $callback): mixed
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
