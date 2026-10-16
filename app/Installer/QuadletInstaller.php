<?php

namespace App\Installer;

use Foxws\Podman\Exceptions\QuadletRenderException;
use Foxws\Podman\Support\PodmanRenderedPreset;
use Illuminate\Support\Facades\File;

/**
 * Installs rendered presets into the host user's Quadlet and systemd user
 * folders, mounted into the installer. Does what "podman quadlet install"
 * does, which the remote Podman client can't do yet: each unit of a
 * ".quadlets" file is written to its own file.
 */
class QuadletInstaller
{
    public function __construct(
        protected string $quadletPath,
        protected string $unitPath,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('installer.quadlet_path'), config('installer.unit_path'));
    }

    /**
     * @param  list<PodmanRenderedPreset>  $presets
     */
    public function install(array $presets): InstalledUnits
    {
        $services = [];
        $start = [];
        $enable = [];

        File::ensureDirectoryExists($this->quadletPath);

        foreach ($presets as $preset) {
            try {
                $units = $preset->units();
            } catch (QuadletRenderException $exception) {
                throw ServicesException::invalidQuadlets($exception->getMessage());
            }

            foreach ($units as $unit) {
                File::put("{$this->quadletPath}/{$unit->fileName()}", $unit->contents);

                $services[] = $unit->serviceName();

                if ($unit->type->isRunnable()) {
                    $start[] = $unit->serviceName();
                }
            }

            foreach ($preset->systemdUnits as $file) {
                File::ensureDirectoryExists($this->unitPath);
                File::copy($file, "{$this->unitPath}/".basename($file));
            }

            foreach ([...$preset->systemdUnitsOfType('socket'), ...$preset->systemdUnitsOfType('timer')] as $file) {
                $enable[] = basename($file);
            }
        }

        return new InstalledUnits(
            services: array_values(array_unique($services)),
            start: array_values(array_unique($start)),
            enable: array_values(array_unique($enable)),
        );
    }
}
