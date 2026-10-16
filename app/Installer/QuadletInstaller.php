<?php

namespace App\Installer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Installs rendered presets into the host user's Quadlet and systemd user
 * folders, mounted into the installer. Does what "podman quadlet install"
 * does, which the remote Podman client can't do yet: each ".quadlets" file
 * holds several units, separated by "---" lines and named by their
 * "# FileName=" line, and each one is written to its own file.
 */
class QuadletInstaller
{
    /**
     * The service systemd generates for each Quadlet unit type.
     */
    protected const array SERVICE_SUFFIXES = [
        'container' => '',
        'kube' => '',
        'pod' => '-pod',
        'volume' => '-volume',
        'network' => '-network',
        'build' => '-build',
        'image' => '-image',
        'artifact' => '-artifact',
    ];

    public function __construct(
        protected string $quadletPath,
        protected string $unitPath,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('installer.quadlet_path'), config('installer.unit_path'));
    }

    /**
     * @param  list<string>  $folders  The folders the presets were rendered to.
     */
    public function install(array $folders): InstalledUnits
    {
        $services = [];
        $start = [];
        $enable = [];

        File::ensureDirectoryExists($this->quadletPath);

        foreach ($folders as $folder) {
            foreach (File::files($folder) as $file) {
                $extension = $file->getExtension();

                if ($extension === 'quadlets') {
                    foreach ($this->split($file->getPathname()) as [$name, $type, $contents]) {
                        File::put("{$this->quadletPath}/{$name}.{$type}", $contents);

                        $services[] = $service = $name.self::SERVICE_SUFFIXES[$type].'.service';

                        if (in_array($type, ['container', 'kube', 'pod'], true)) {
                            $start[] = $service;
                        }
                    }

                    continue;
                }

                if (in_array($extension, ['socket', 'timer', 'service'], true)) {
                    File::ensureDirectoryExists($this->unitPath);
                    File::copy($file->getPathname(), "{$this->unitPath}/{$file->getFilename()}");

                    if ($extension !== 'service') {
                        $enable[] = $file->getFilename();
                    }
                }
            }
        }

        return new InstalledUnits(
            services: array_values(array_unique($services)),
            start: array_values(array_unique($start)),
            enable: array_values(array_unique($enable)),
        );
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}> The name, type and contents of each unit.
     */
    public function split(string $file): array
    {
        $units = [];

        foreach (preg_split('/^---$/m', (string) file_get_contents($file)) as $chunk) {
            $chunk = Str::trim($chunk, "\n");

            if ($chunk === '') {
                continue;
            }

            preg_match_all('/^\[([A-Za-z]+)\]$/m', $chunk, $sections);

            $type = collect($sections[1])
                ->map(fn (string $section): string => Str::lower($section))
                ->first(fn (string $section): bool => array_key_exists($section, self::SERVICE_SUFFIXES));

            if ($type === null || ! preg_match('/^# FileName=([A-Za-z0-9_.@-]+)$/m', $chunk, $name)) {
                throw ServicesException::invalidQuadlets($file);
            }

            $units[] = [$name[1], $type, "{$chunk}\n"];
        }

        return $units;
    }
}
