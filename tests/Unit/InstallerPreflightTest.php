<?php

declare(strict_types=1);

use App\Installer\CheckStatus;
use App\Installer\InstallerPreflight;
use App\Installer\RequirementCheck;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * @return array<string, CheckStatus>
 */
function preflightStatuses(string $manifest, array $files = []): array
{
    $path = installerFolder(['manifest.yml' => $manifest, 'a.md' => 'Body', ...$files]);

    return collect(app(InstallerPreflight::class)->check($path)->checks)
        ->mapWithKeys(fn (RequirementCheck $check): array => [$check->id => $check->status])
        ->all();
}

it('fails on an invalid manifest', function () {
    $checks = app(InstallerPreflight::class)->check(installerFolder(['manifest.yml' => 'name: App']))->checks;

    expect($checks)->toHaveCount(1)
        ->and($checks[0]->status)->toBe(CheckStatus::Fail)
        ->and($checks[0]->message)->toContain('needs a "name" and a list of "steps"');
});

it('checks the installer versions the app supports', function (string $version, CheckStatus $status) {
    config(['installer.version' => $version]);

    expect(preflightStatuses("name: App\ninstaller: '^2.0'\nsteps:\n  - { id: first, file: a.md }")['version'])->toBe($status);
})->with([
    'matching' => ['2.1.0', CheckStatus::Ok],
    'too old' => ['1.4.0', CheckStatus::Fail],
    'development build' => ['dev', CheckStatus::Warn],
]);

it('only checks the mounts the steps need', function () {
    Process::fake();

    expect(preflightStatuses("name: App\nsteps:\n  - { id: first, file: a.md }"))->toBe(['manifest' => CheckStatus::Ok]);

    Process::assertNothingRan();
});

it('fails a services installer without its mounts', function () {
    Process::fake(['*' => Process::result(output: '{}')]);
    config([
        'installer.quadlet_path' => '/nonexistent/quadlets',
        'installer.unit_path' => '/nonexistent/units',
        'installer.dbus_address' => null,
        'installer.workspace' => installerFolder([]),
    ]);

    expect(preflightStatuses("name: App\nsteps:\n  - { id: services, type: services, file: a.md }"))->toBe([
        'manifest' => CheckStatus::Ok,
        'podman' => CheckStatus::Ok,
        'quadlets' => CheckStatus::Fail,
        'units' => CheckStatus::Fail,
        'bus' => CheckStatus::Fail,
        'env' => CheckStatus::Warn,
    ]);
});

it('passes a services installer with its mounts', function () {
    Process::fake(['*' => Process::result(output: '{}')]);
    $host = installerFolder(['bus' => '']);
    File::ensureDirectoryExists("{$host}/quadlets");
    File::ensureDirectoryExists("{$host}/units");
    config([
        'installer.quadlet_path' => "{$host}/quadlets",
        'installer.unit_path' => "{$host}/units",
        'installer.dbus_address' => "unix:path={$host}/bus",
        'installer.workspace' => installerFolder(['.env' => 'APP_NAME=demo']),
    ]);

    expect(preflightStatuses("name: App\nsteps:\n  - { id: services, type: services, file: a.md }"))
        ->each->toBe(CheckStatus::Ok);
});

it('fails a command installer when podman cannot be reached', function () {
    Process::fake(['*' => Process::result(exitCode: 125)]);

    expect(preflightStatuses("name: App\nsteps:\n  - { id: seed, type: command, file: a.md, run: { container: app, command: [php] } }")['podman'])
        ->toBe(CheckStatus::Fail);
});
