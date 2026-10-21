<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureInstallerToken;
use App\Installer\Source;
use App\Livewire\ChooseSource;
use App\Livewire\Installer;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

/**
 * Start the installer without an installer folder of its own, with an empty
 * workspace, and fake a repository with the given manifest. Returns the
 * workspace.
 */
function fakeRepository(string $manifest = "name: Acme\nsteps:\n  - { id: welcome, file: welcome.md }"): string
{
    $workspace = installerFolder([]);

    config([
        'installer.path' => installerFolder([]),
        'installer.workspace' => $workspace,
        'installer.version' => '2.0.0',
    ]);

    Process::fake(function (PendingProcess $process) use ($workspace, $manifest) {
        $command = implode(' ', $process->command);

        if (str_contains($command, 'ls-remote')) {
            return Process::result(output: implode("\n", [
                "ref: refs/heads/main\tHEAD",
                str_repeat('a', 40)."\trefs/heads/main",
                str_repeat('b', 40)."\trefs/tags/v1.2.0",
            ]));
        }

        if (str_contains($command, 'checkout')) {
            File::ensureDirectoryExists("{$workspace}/installer");
            File::put("{$workspace}/installer/manifest.yml", $manifest);
            File::put("{$workspace}/installer/welcome.md", 'Welcome to Acme');
        }

        if (str_contains($command, 'rev-parse')) {
            return Process::result(output: str_repeat('b', 40));
        }

        return Process::result();
    });

    return $workspace;
}

it('installs a tag of a repository and shows where it came from', function () {
    fakeRepository("name: Acme\ndescription: The Acme app\nsteps:\n  - { id: welcome, file: welcome.md }");

    Livewire::test(ChooseSource::class)
        ->set('url', 'https://github.com/acme/app.git')
        ->call('fetch')
        ->assertSet('current', 1)
        ->assertSet('refType', 'tag')
        ->assertSet('ref', 'v1.2.0')
        ->assertSee(str_repeat('b', 40))
        ->call('check')
        ->assertSet('current', 2)
        ->assertSee('The installer of Acme is valid, with 1 step')
        ->call('start')
        ->assertRedirect(route('installer'));

    Process::assertRan(fn (PendingProcess $process): bool => in_array('v1.2.0', $process->command, true) && in_array('fetch', $process->command, true));

    Livewire::test(Installer::class)
        ->assertSee('Welcome to Acme')
        ->assertSee('The Acme app')
        ->assertSee('github.com/acme/app @ v1.2.0 (bbbbbbb)')
        ->assertSee('Choose another version');
});

it('installs the latest commit of a branch', function () {
    fakeRepository();

    Livewire::test(ChooseSource::class)
        ->set('url', 'https://github.com/acme/app.git')
        ->call('fetch')
        ->set('refType', 'branch')
        ->assertSet('ref', 'main')
        ->call('check')
        ->assertSet('checkedOut.ref', 'main')
        ->assertSet('checkedOut.ref_type', 'branch');
});

it('only accepts a full commit hash', function () {
    fakeRepository();

    Livewire::test(ChooseSource::class)
        ->set('url', 'https://github.com/acme/app.git')
        ->call('fetch')
        ->set('refType', 'commit')
        ->set('ref', 'abc1234')
        ->call('check')
        ->assertHasErrors('ref')
        ->assertSet('current', 1);

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('fetch', $process->command, true));
});

it('only accepts https repositories', function () {
    fakeRepository();

    Livewire::test(ChooseSource::class)
        ->set('url', 'git@github.com:acme/app.git')
        ->call('fetch')
        ->assertHasErrors('url')
        ->assertSet('current', 0);

    Process::assertNothingRan();
});

it('keeps a typed token out of the component', function () {
    fakeRepository();

    Livewire::test(ChooseSource::class)
        ->set('url', 'https://github.com/acme/private.git')
        ->set('token', 'secret-token')
        ->call('fetch')
        ->assertSet('token', '')
        ->assertSet('current', 1);

    expect(Source::token())->toBe('secret-token');
    Process::assertRan(fn (PendingProcess $process): bool => ($process->environment['GIT_CONFIG_VALUE_0'] ?? null) === 'Authorization: Basic '.base64_encode('x-access-token:secret-token'));
});

it("doesn't check out into a workspace with other files", function () {
    $workspace = fakeRepository();
    File::put("{$workspace}/notes.txt", 'Mine');

    Livewire::test(ChooseSource::class)
        ->set('url', 'https://github.com/acme/app.git')
        ->call('fetch')
        ->call('check')
        ->assertHasErrors('ref')
        ->assertSee("isn't empty")
        ->assertSet('current', 1);

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('fetch', $process->command, true));
});

it("doesn't start an installer that can't work here", function () {
    fakeRepository("name: Acme\ninstaller: '^3.0'\nsteps:\n  - { id: welcome, file: welcome.md }");

    Livewire::test(ChooseSource::class)
        ->set('url', 'https://github.com/acme/app.git')
        ->call('fetch')
        ->call('check')
        ->assertSee('Acme needs installer ^3.0, but this is installer 2.0.0')
        ->call('start')
        ->assertNoRedirect();

    expect(Source::current())->toBeNull();
});

it('asks for the source before the steps', function () {
    fakeRepository();

    $this->withSession([EnsureInstallerToken::SessionKey => true])
        ->get('/')
        ->assertRedirect(route('source'));
});

it('skips the source with an installer folder of its own', function () {
    $this->withSession([EnsureInstallerToken::SessionKey => true])
        ->get('/source')
        ->assertRedirect(route('installer'));
});

it('needs the one-time link for the source', function () {
    fakeRepository();

    $this->get('/source')->assertForbidden();
});
