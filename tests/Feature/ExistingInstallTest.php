<?php

declare(strict_types=1);

use App\Installer\Manifest;
use App\Livewire\Installer;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/existing-'.bin2hex(random_bytes(4));

    File::ensureDirectoryExists("{$this->root}/workspace");
    File::ensureDirectoryExists("{$this->root}/quadlets");
    File::put("{$this->root}/workspace/.env", "APP_NAME=Acme\nAPP_URL=https://acme.test\n");

    config([
        'installer.workspace' => "{$this->root}/workspace",
        'installer.quadlet_path' => "{$this->root}/quadlets",
        'installer.unit_path' => "{$this->root}/units",
    ]);

    app()->instance(Manifest::class, Manifest::fromPath(dirname(__DIR__).'/fixtures/installer-existing'));

    $info = file_get_contents(dirname(__DIR__).'/fixtures/podman/rootless.json');

    Process::fake(fn (PendingProcess $process) => match (true) {
        in_array('info', $process->command, true) => Process::result(output: $info),
        in_array('org.freedesktop.systemd1.Manager.LoadUnit', $process->command, true) => Process::result(output: 'object path "/org/freedesktop/systemd1/unit/acme"'),
        in_array('org.freedesktop.DBus.Properties.Get', $process->command, true) => Process::result(output: 'variant string "active"'),
        default => Process::result(),
    });
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('continues when the app is not installed yet', function () {
    Livewire::test(Installer::class)
        ->assertSee("acme isn't installed on this host yet")
        ->call('next')
        ->assertSet('current', 1);
});

it('stops when the app is installed already', function () {
    File::put("{$this->root}/quadlets/acme.container", "[Container]\n");

    Livewire::test(Installer::class)
        ->assertSee('acme is installed on this host already. The installer is only for a first install')
        ->assertSee('INSTALLER_ALLOW_EXISTING=true')
        ->call('next')
        ->assertSet('current', 0);
});

it('only warns when allowed to install over an existing install', function () {
    File::put("{$this->root}/quadlets/acme.container", "[Container]\n");
    config(['installer.allow_existing' => true]);

    Livewire::test(Installer::class)
        ->assertSee('The installer will reinstall and restart its services')
        ->call('next')
        ->assertSet('current', 1);
});

it('does not stop on the services this run installed', function () {
    Livewire::test(Installer::class)
        ->call('next')
        ->call('installServices')
        ->assertHasNoErrors()
        ->call('previous')
        ->assertSet('current', 0)
        ->assertDontSee('installed on this host already')
        ->call('next')
        ->assertSet('current', 1);

    expect(File::exists("{$this->root}/quadlets/acme.container"))->toBeTrue();
});
