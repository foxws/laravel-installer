<?php

declare(strict_types=1);

use App\Installer\Manifest;
use App\Livewire\Installer;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    app()->instance(Manifest::class, Manifest::fromPath(dirname(__DIR__).'/fixtures/installer-command'));
});

function adminValues(): array
{
    return ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'correct-horse-battery'];
}

it('does not continue before the command ran', function () {
    Livewire::test(Installer::class)
        ->call('next')
        ->assertSet('current', 0);
});

it('validates the inputs before running anything', function () {
    Process::fake();

    Livewire::test(Installer::class)
        ->set('values', ['name' => 'Ada', 'email' => 'not-an-email', 'password' => 'short'])
        ->call('runCommand')
        ->assertHasErrors(['values.email', 'values.password']);

    Process::assertNothingRan();
});

it('runs the command with the secret on stdin, never in the arguments', function () {
    Process::fake(fn (PendingProcess $process) => in_array('users:exists', $process->command, true)
        ? Process::result(exitCode: 1)
        : Process::result(output: 'Created ada@example.com with correct-horse-battery'));

    Livewire::test(Installer::class)
        ->set('values', adminValues())
        ->call('runCommand')
        ->assertHasNoErrors()
        ->assertSet('completed', ['admin'])
        ->assertSet('values', ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSet('output', 'Created ada@example.com with ••••••••')
        ->assertDontSee('correct-horse-battery')
        ->call('next')
        ->assertSet('current', 1);

    Process::assertRan(fn (PendingProcess $process) => $process->command === [
        'podman', 'exec', '--interactive', 'systemd-demo',
        'php', 'artisan', 'users:create', '--super-admin', '--no-interaction', '--name=Ada', '--email=ada@example.com',
    ] && $process->input === 'correct-horse-battery');
});

it('skips the command when it was already done', function () {
    Process::fake();

    Livewire::test(Installer::class)
        ->set('values', adminValues())
        ->call('runCommand')
        ->assertSet('completed', ['admin'])
        ->assertSee('already done');

    Process::assertRanTimes(fn (PendingProcess $process) => in_array('users:create', $process->command, true), 0);
});

it('stays on the step when the command fails', function () {
    Process::fake(fn (PendingProcess $process) => Process::result(errorOutput: 'Boom', exitCode: 1));

    Livewire::test(Installer::class)
        ->set('values', adminValues())
        ->call('runCommand')
        ->assertHasErrors('command')
        ->assertSet('completed', [])
        ->assertSet('output', 'Boom')
        ->assertSet('values.password', null)
        ->call('next')
        ->assertSet('current', 0);
});

it('passes secrets to a seeder through the environment by name', function () {
    Process::fake(fn (PendingProcess $process) => in_array('users:exists', $process->command, true)
        ? Process::result(exitCode: 1)
        : Process::result());

    Livewire::test(Installer::class)
        ->set('values', adminValues())
        ->call('runCommand')
        ->call('next')
        ->set('values', ['token' => 's3cret'])
        ->call('runCommand')
        ->assertSet('completed', ['admin', 'seed']);

    Process::assertRan(fn (PendingProcess $process) => $process->command === [
        'podman', 'exec', '--env', 'INSTALLER_TOKEN', 'systemd-demo',
        'php', 'artisan', 'db:seed', '--class=InstallerSeeder', '--force',
    ] && $process->environment === ['INSTALLER_TOKEN' => 's3cret']);
});
