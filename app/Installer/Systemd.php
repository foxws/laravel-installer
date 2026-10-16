<?php

namespace App\Installer;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

/**
 * Talks to the host user's systemd through their D-Bus session bus, mounted
 * into the installer, with "dbus-send". The image has no systemd, so this
 * takes the place of "systemctl --user".
 */
class Systemd
{
    protected const string DESTINATION = 'org.freedesktop.systemd1';

    protected const string MANAGER = '/org/freedesktop/systemd1';

    /**
     * Reload the unit files, which runs the Quadlet generator ("systemctl
     * --user daemon-reload").
     */
    public function reload(): void
    {
        $this->call('reload the units', self::MANAGER, 'org.freedesktop.systemd1.Manager.Reload');
    }

    /**
     * Enable sockets and timers, so they start on boot ("systemctl --user
     * enable").
     *
     * @param  list<string>  $units
     */
    public function enable(array $units): void
    {
        if ($units === []) {
            return;
        }

        $this->call('enable '.implode(', ', $units), self::MANAGER, 'org.freedesktop.systemd1.Manager.EnableUnitFiles', [
            'array:string:'.implode(',', $units),
            'boolean:false',
            'boolean:true',
        ]);
    }

    /**
     * Queue a unit to start, without waiting for it: building an image can
     * take a while, so its state is followed with activeState() instead.
     */
    public function start(string $unit): void
    {
        $this->call("start {$unit}", self::MANAGER, 'org.freedesktop.systemd1.Manager.StartUnit', [
            "string:{$unit}",
            'string:replace',
        ]);
    }

    /**
     * The unit's state, such as "active", "activating", "inactive" or
     * "failed".
     */
    public function activeState(string $unit): string
    {
        $path = $this->call("load {$unit}", self::MANAGER, 'org.freedesktop.systemd1.Manager.LoadUnit', ["string:{$unit}"]);

        if (! preg_match('/object path "([^"]+)"/', $path->output(), $match)) {
            throw ServicesException::systemd("load {$unit}", 'no unit path in its reply.');
        }

        $state = $this->call("read the state of {$unit}", $match[1], 'org.freedesktop.DBus.Properties.Get', [
            'string:org.freedesktop.systemd1.Unit',
            'string:ActiveState',
        ]);

        return preg_match('/string "([a-z-]+)"/', $state->output(), $match) ? $match[1] : 'unknown';
    }

    /**
     * @param  list<string>  $arguments
     */
    protected function call(string $action, string $path, string $method, array $arguments = []): ProcessResult
    {
        $result = Process::timeout(30)->run([
            config('installer.dbus_send'),
            '--session',
            '--print-reply',
            '--dest='.self::DESTINATION,
            $path,
            $method,
            ...$arguments,
        ]);

        if (! $result->successful()) {
            throw ServicesException::systemd($action, trim($result->errorOutput()) ?: "exit code {$result->exitCode()}");
        }

        return $result;
    }
}
