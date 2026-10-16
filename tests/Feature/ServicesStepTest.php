<?php

use App\Installer\Manifest;
use App\Livewire\Installer;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/services-'.bin2hex(random_bytes(4));

    File::ensureDirectoryExists("{$this->root}/workspace");
    File::put("{$this->root}/workspace/.env", "APP_NAME=Acme\nAPP_URL=https://acme.test\nPODMAN_WORKING_PATH=/home/ada/acme");

    config([
        'installer.workspace' => "{$this->root}/workspace",
        'installer.quadlet_path' => "{$this->root}/quadlets",
        'installer.unit_path' => "{$this->root}/units",
    ]);

    app()->instance(Manifest::class, Manifest::fromPath(dirname(__DIR__).'/fixtures/installer-services'));
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

/**
 * Fake "dbus-send", with every unit in the given state.
 */
function fakeSystemd(string $state): void
{
    Process::fake([
        '*LoadUnit*' => Process::result(output: 'object path "/org/freedesktop/systemd1/unit/acme"'),
        '*Properties.Get*' => Process::result(output: "variant string \"{$state}\""),
        '*' => Process::result(),
    ]);
}

it('shows what it will install before it does', function () {
    Process::fake();

    Livewire::test(Installer::class)
        ->assertSee('Acme (https://acme.test)')
        ->assertSee('/home/ada/acme')
        ->assertSee('production')
        ->call('next')
        ->assertSet('current', 0);

    Process::assertNothingRan();
});

it('renders, installs and starts the services, then continues once they run', function () {
    fakeSystemd('active');

    Livewire::test(Installer::class)
        ->call('installServices')
        ->assertHasNoErrors()
        ->assertSee('acme.service')
        ->assertSee('Running')
        ->call('next')
        ->assertSet('current', 1);

    expect(File::exists("{$this->root}/workspace/podman/production/app.quadlets"))->toBeTrue()
        ->and(File::exists("{$this->root}/quadlets/acme.container"))->toBeTrue();

    Process::assertRan(fn (PendingProcess $process) => in_array('org.freedesktop.systemd1.Manager.Reload', $process->command, true));
    Process::assertRan(fn (PendingProcess $process) => in_array('string:acme.service', $process->command, true)
        && in_array('org.freedesktop.systemd1.Manager.StartUnit', $process->command, true));
});

it('waits while the services start', function () {
    fakeSystemd('activating');

    Livewire::test(Installer::class)
        ->call('installServices')
        ->assertSee('Starting…')
        ->call('next')
        ->assertSet('current', 0);
});

it('offers to try again when a service failed', function () {
    fakeSystemd('failed');

    Livewire::test(Installer::class)
        ->call('installServices')
        ->assertSee('A service failed to start')
        ->assertSee('Try again')
        ->call('next')
        ->assertSet('current', 0);
});

it('shows why systemd could not be reached', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Failed to open connection to "session" message bus', exitCode: 1)]);

    Livewire::test(Installer::class)
        ->call('installServices')
        ->assertHasErrors('services')
        ->assertSee('Failed to open connection')
        ->assertSet('services', []);
});

it('explains how to mount a workspace when there is none', function () {
    config(['installer.workspace' => null]);

    Livewire::test(Installer::class)
        ->assertSee('No workspace is mounted')
        ->assertDontSee('Install and start');
});
