<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureInstallerToken;
use App\Livewire\Installer;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function useDoctorReport(string $name): void
{
    config(['installer.doctor_report' => dirname(__DIR__)."/fixtures/doctor/{$name}.json"]);
}

it('shows the first step', function () {
    $this->withoutVite()
        ->withSession([EnsureInstallerToken::SessionKey => true])
        ->get('/')
        ->assertOk()
        ->assertSee('Example installer')
        ->assertSee('This installer sets up the app');
});

it('moves through the steps', function () {
    useDoctorReport('warnings');

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSet('current', 1)
        ->assertSee('Linger is off')
        ->assertSee('loginctl enable-linger me')
        ->call('next')
        ->assertSet('current', 2)
        ->assertSee('The app is installed')
        ->call('next')
        ->assertSet('current', 2)
        ->call('previous')
        ->assertSet('current', 1);
});

it('stops at the requirements while the host has problems', function () {
    useDoctorReport('failures');

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSee('me has no entry in /etc/subuid')
        ->assertSee('Fix the problems above on the host')
        ->call('next')
        ->assertSet('current', 1);
});

it('checks the host through the podman socket without a host report', function () {
    useDoctorReport('missing');

    Process::fake([
        '*' => Process::result(output: file_get_contents(dirname(__DIR__).'/fixtures/podman/rootless.json')),
    ]);

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSee('Podman 5.6.2 with Quadlet')
        ->assertSee("can't see whether linger is on")
        ->call('next')
        ->assertSet('current', 2);

    Process::assertRan(fn (PendingProcess $process) => $process->command === ['podman', 'info', '--format', 'json']);
});

it('stops at the requirements when the podman socket cannot be reached', function () {
    useDoctorReport('missing');

    Process::fake(['*' => Process::result(exitCode: 125)]);

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSee("can't reach Podman through its socket")
        ->call('next')
        ->assertSet('current', 1);
});

it('does not let the browser skip steps', function () {
    useDoctorReport('failures');

    Livewire::test(Installer::class)->set('current', 2);
})->throws(CannotUpdateLockedPropertyException::class);
