<?php

declare(strict_types=1);

use App\Installer\Manifest;
use App\Livewire\Installer;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/secrets-'.bin2hex(random_bytes(4));
    $quadlets = "{$this->root}/workspace/containers/stubs/demo/quadlets";

    File::ensureDirectoryExists($quadlets);
    File::put("{$this->root}/workspace/.env", "APP_NAME=Acme\nDB_PASSWORD=from-the-env-file\n");
    File::put("{$quadlets}/app.quadlets", <<<'QUADLETS'
        # FileName={{application}}
        [Container]
        Secret={{application}}-env,target=/app/.env,mode=0400
        ---
        # FileName={{application}}-pgsql
        [Container]
        Secret={{application}}-pgsql-password,type=env,target=POSTGRES_PASSWORD
        Secret={{application}}-pgsql-password,type=env,target=PGPASSWORD
        Secret={{application}}-pgsql-user,type=env,target=POSTGRES_USER
        QUADLETS);

    config(['installer.workspace' => "{$this->root}/workspace"]);

    app()->instance(Manifest::class, Manifest::fromPath(dirname(__DIR__).'/fixtures/installer-secrets'));
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

/**
 * Fake "podman secret", which remembers the secrets it created.
 *
 * @param  list<string>  $existing
 */
function fakePodmanSecrets(array $existing = []): void
{
    Process::fake(function (PendingProcess $process) use (&$existing) {
        if (($process->command[2] ?? null) === 'create') {
            $existing[] = $process->command[4];
        }

        return Process::result(output: implode("\n", $existing));
    });
}

it('lists the secrets the services need, and where their values come from', function () {
    fakePodmanSecrets(['acme-pgsql-user']);

    Livewire::test(Installer::class)
        ->assertSee('acme-env')
        ->assertSee('The file .env')
        ->assertSee('DB_PASSWORD in the app&#039;s .env', false)
        ->assertSee('For POSTGRES_PASSWORD, PGPASSWORD')
        ->assertSee('Exists')
        ->assertSee('Missing')
        ->call('next')
        ->assertSet('current', 0);
});

it('creates the missing secrets, then continues', function () {
    fakePodmanSecrets();

    Livewire::test(Installer::class)
        ->set('secretValues', ['acme-pgsql-user' => 'typed-in'])
        ->call('createSecrets')
        ->assertHasNoErrors()
        ->assertSet('secretValues', [])
        ->assertDontSee('Missing')
        ->call('next')
        ->assertSet('current', 1);

    Process::assertRan(fn (PendingProcess $process) => $process->command === ['podman', 'secret', 'create', '--replace', 'acme-env', "{$this->root}/workspace/.env"]);
    Process::assertRan(fn (PendingProcess $process) => $process->command === ['podman', 'secret', 'create', '--replace', 'acme-pgsql-password', '-']
        && $process->input === 'from-the-env-file');
    Process::assertRan(fn (PendingProcess $process) => $process->command === ['podman', 'secret', 'create', '--replace', 'acme-pgsql-user', '-']
        && $process->input === 'typed-in');
});

it('generates a value when none is given', function () {
    fakePodmanSecrets(['acme-env', 'acme-pgsql-password']);

    Livewire::test(Installer::class)->call('createSecrets');

    Process::assertRan(fn (PendingProcess $process) => ($process->command[4] ?? null) === 'acme-pgsql-user'
        && strlen((string) $process->input) === 40);
});

it('keeps the secrets that exist, unless asked to replace them', function () {
    fakePodmanSecrets(['acme-env', 'acme-pgsql-password', 'acme-pgsql-user']);

    Livewire::test(Installer::class)
        ->assertDontSee('Create secrets')
        ->set('replaceSecrets', true)
        ->call('createSecrets');

    Process::assertRanTimes(fn (PendingProcess $process) => ($process->command[2] ?? null) === 'create', 3);
});

it('shows what podman said when it fails', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Error: cannot connect to Podman', exitCode: 125)]);

    Livewire::test(Installer::class)
        ->assertSee('Error: cannot connect to Podman')
        ->call('next')
        ->assertSet('current', 0);
});

it('explains how to mount a workspace when there is none', function () {
    config(['installer.workspace' => null]);

    Livewire::test(Installer::class)
        ->assertSee('No workspace is mounted');
});
