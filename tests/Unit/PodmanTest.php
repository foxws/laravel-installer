<?php

declare(strict_types=1);

use App\Installer\Podman;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('wakes an on-demand app from a container on the host network, with its own image', function () {
    Process::fake([
        '*inspect*' => Process::result(output: "ghcr.io/acme/my-project-installer:latest\n"),
        '*' => Process::result(),
    ]);

    (new Podman)->wake('127.0.0.1:8001');

    Process::assertRan(fn (PendingProcess $process) => $process->command === [
        'podman', 'run', '--rm', '--detach', '--network=host',
        '--entrypoint', 'curl', 'ghcr.io/acme/my-project-installer:latest',
        '--silent', '--output', '/dev/null', '--max-time', '900', 'http://127.0.0.1:8001/up',
    ]);
});

it('uses the configured image', function () {
    Process::fake();
    config(['installer.image' => 'localhost/my-project-installer']);

    expect((new Podman)->ownImage())->toBe('localhost/my-project-installer');

    Process::assertNothingRan();
});
