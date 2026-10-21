<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureInstallerToken;
use App\Livewire\Installer;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Fake "podman info" through the socket, with the given values changed.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakePodmanInfo(array $overrides = []): void
{
    $info = json_decode(file_get_contents(dirname(__DIR__).'/fixtures/podman/rootless.json'), true);

    foreach ($overrides as $key => $value) {
        data_set($info, $key, $value);
    }

    Process::fake(['*' => Process::result(output: json_encode($info))]);
}

it('shows the first step', function () {
    $this->withoutVite()
        ->withSession([EnsureInstallerToken::SessionKey => true])
        ->get('/')
        ->assertOk()
        ->assertSee('Example installer')
        ->assertSee('This installer sets up the app')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('moves through the steps', function () {
    fakePodmanInfo();

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSet('current', 1)
        ->assertSee("can't see whether linger is on")
        ->assertSee('loginctl enable-linger')
        ->call('next')
        ->assertSet('current', 2)
        ->assertSee('The app is installed')
        ->call('next')
        ->assertSet('current', 2)
        ->call('previous')
        ->assertSet('current', 1);
});

it('stops at the requirements while the host has problems', function () {
    fakePodmanInfo(['host.idMappings.uidmap' => [['container_id' => 0, 'host_id' => 1000, 'size' => 1]]]);

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSee('no subordinate user and group IDs')
        ->assertSeeHtml('alert-error')
        ->assertSee('Fix the problems above on the host')
        ->call('next')
        ->assertSet('current', 1);
});

it('checks the host through the podman socket', function () {
    fakePodmanInfo();

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSee('Podman 5.6.2 with Quadlet')
        ->assertSee("can't see whether linger is on")
        ->call('next')
        ->assertSet('current', 2);

    Process::assertRan(fn (PendingProcess $process) => $process->command === ['podman', 'info', '--format', 'json']);
});

it('stops at the requirements when the podman socket cannot be reached', function () {
    Process::fake(['*' => Process::result(exitCode: 125)]);

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSee("can't reach Podman through its socket")
        ->call('next')
        ->assertSet('current', 1);
});

it('does not let the browser skip steps', function () {
    Livewire::test(Installer::class)->set('current', 2);
})->throws(CannotUpdateLockedPropertyException::class);

it('skips a step that stops the installer when skipping is allowed', function () {
    config(['installer.allow_skipping' => true]);
    fakePodmanInfo(['host.idMappings.uidmap' => [['container_id' => 0, 'host_id' => 1000, 'size' => 1]]]);

    Livewire::test(Installer::class)
        ->assertSee('Steps can be skipped')
        ->call('next')
        ->assertSee('Skip step')
        ->call('next')
        ->assertSet('current', 1)
        ->call('skip')
        ->assertSet('current', 2);
});

it("doesn't skip steps unless skipping is allowed", function () {
    fakePodmanInfo(['host.idMappings.uidmap' => [['container_id' => 0, 'host_id' => 1000, 'size' => 1]]]);

    Livewire::test(Installer::class)
        ->assertDontSee('Steps can be skipped')
        ->call('next')
        ->assertDontSee('Skip step')
        ->call('skip')
        ->assertSet('current', 1);
});
