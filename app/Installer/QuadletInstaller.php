<?php

declare(strict_types=1);

namespace App\Installer;

use Foxws\Podman\Exceptions\QuadletRenderException;
use Foxws\Podman\Support\PodmanRenderedPreset;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
        return new self(Config::string('installer.quadlet_path'), Config::string('installer.unit_path'));
    }

    /**
     * Install the presets' units, and say which ones to start:
     *
     * - Containers a timer starts, such as a scheduler, are left to their
     *   timer, since they exit once they ran.
     * - With an on-demand socket, nothing is started directly. The socket is
     *   woken by connecting to its address instead, like a visitor would, so
     *   it hands itself to its proxy service. The proxy requires the app,
     *   which wants the rest, so they keep running until it's idle. Started
     *   on their own, the proxy gets no socket, and services with
     *   "StopWhenUnneeded=yes" stop again right away.
     * - Otherwise, the containers, pods and kube units are started, and they
     *   start the volumes, networks and builds they depend on.
     *
     * @param  list<PodmanRenderedPreset>  $presets
     */
    public function install(array $presets): InstalledUnits
    {
        $runnable = [];
        $sockets = [];
        $timers = [];
        $plainServices = [];

        File::ensureDirectoryExists($this->quadletPath);

        foreach ($presets as $preset) {
            try {
                $units = $preset->units();
            } catch (QuadletRenderException $exception) {
                throw ServicesException::invalidQuadlets($exception->getMessage());
            }

            foreach ($units as $unit) {
                File::put("{$this->quadletPath}/{$unit->fileName()}", $unit->contents);

                if ($unit->type->isRunnable()) {
                    $runnable[] = $unit->serviceName();
                }
            }

            foreach ($preset->systemdUnits as $file) {
                File::ensureDirectoryExists($this->unitPath);
                File::copy($file, "{$this->unitPath}/".basename($file));
            }

            $sockets = [...$sockets, ...array_map(basename(...), $preset->systemdUnitsOfType('socket'))];
            $timers = [...$timers, ...array_map(basename(...), $preset->systemdUnitsOfType('timer'))];
            $plainServices = [...$plainServices, ...array_map(basename(...), $preset->systemdUnitsOfType('service'))];
        }

        $proxies = array_values(array_filter(
            array_map(fn (string $socket): string => Str::replaceLast('.socket', '.service', $socket), $sockets),
            fn (string $service): bool => in_array($service, $plainServices, true),
        ));

        $timerServices = array_map(fn (string $timer): string => Str::replaceLast('.timer', '.service', $timer), $timers);

        $start = $proxies !== [] ? [] : array_values(array_unique(array_diff($runnable, $timerServices)));
        $enable = array_values(array_unique([...$sockets, ...$timers]));

        $wake = array_values(array_filter(array_map(
            fn (string $proxy): ?string => $this->listenAddress("{$this->unitPath}/".Str::replaceLast('.service', '.socket', $proxy)),
            $proxies,
        )));

        return new InstalledUnits(
            services: [...$start, ...$proxies, ...$enable],
            start: $start,
            enable: $enable,
            wake: $wake,
        );
    }

    /**
     * The TCP address a socket listens on, as a host and port to connect to:
     * a bare port or a wildcard address becomes "127.0.0.1". Null for
     * sockets on a file path.
     */
    public function listenAddress(string $socket): ?string
    {
        if (! preg_match('/^ListenStream=(.+)$/m', (string) File::get($socket), $match)) {
            return null;
        }

        $listen = Str::trim($match[1]);

        if (Str::startsWith($listen, ['/', '@'])) {
            return null;
        }

        if (ctype_digit($listen)) {
            return "127.0.0.1:{$listen}";
        }

        $port = Str::afterLast($listen, ':');
        $host = Str::beforeLast($listen, ':');

        return in_array($host, ['0.0.0.0', '[::]', '::', '*'], true) ? "127.0.0.1:{$port}" : "{$host}:{$port}";
    }
}
