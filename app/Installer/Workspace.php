<?php

namespace App\Installer;

use App\Installer\Concerns\InteractsWithPodman;
use Dotenv\Dotenv;
use Illuminate\Support\Arr;
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
}
