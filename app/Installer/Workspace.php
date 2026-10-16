<?php

declare(strict_types=1);

namespace App\Installer;

use App\Installer\Concerns\InteractsWithPodman;
use Dotenv\Dotenv;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * The app's folder on the host, mounted into the installer, and the values
 * of its ".env" file that the installer may read.
 */
class Workspace
{
    use InteractsWithPodman;

    public function __construct(
        public readonly ?string $path,
        public readonly ?string $envFile,
    ) {}

    public static function fromConfig(): self
    {
        $path = config('installer.workspace');

        if (! is_string($path) || $path === '') {
            return new self(null, null);
        }

        $path = Str::rtrim($path, '/');
        $envFile = Config::string('installer.env_file');

        return new self($path, Str::startsWith($envFile, '/') ? $envFile : "{$path}/{$envFile}");
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
}
