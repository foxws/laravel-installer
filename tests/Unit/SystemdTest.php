<?php

declare(strict_types=1);

use App\Installer\ServicesException;
use App\Installer\Systemd;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('reads a unit\'s state over D-Bus', function () {
    Process::fake([
        '*LoadUnit*' => Process::result(output: "method return time=1 sender=:1.2\n   object path \"/org/freedesktop/systemd1/unit/acme_2eservice\"\n"),
        '*Properties.Get*' => Process::result(output: "method return time=1 sender=:1.2\n   variant       string \"activating\"\n"),
    ]);

    expect((new Systemd)->activeState('acme.service'))->toBe('activating');

    Process::assertRan(fn (PendingProcess $process) => $process->command === [
        'dbus-send', '--session', '--print-reply', '--dest=org.freedesktop.systemd1',
        '/org/freedesktop/systemd1/unit/acme_2eservice', 'org.freedesktop.DBus.Properties.Get',
        'string:org.freedesktop.systemd1.Unit', 'string:ActiveState',
    ]);
});

it('enables sockets and timers in one call', function () {
    Process::fake();

    (new Systemd)->enable(['acme-ondemand.socket', 'acme-idle.timer']);

    Process::assertRan(fn (PendingProcess $process) => array_slice($process->command, -4) === [
        'org.freedesktop.systemd1.Manager.EnableUnitFiles',
        'array:string:acme-ondemand.socket,acme-idle.timer',
        'boolean:false',
        'boolean:true',
    ]);
});

it('fails with what systemd said', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Unit acme.service not found.', exitCode: 1)]);

    (new Systemd)->start('acme.service');
})->throws(ServicesException::class, "Systemd couldn't start acme.service: Unit acme.service not found.");
