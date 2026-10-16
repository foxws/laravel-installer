<?php

use App\Installer\ServicesException;
use App\Installer\Workspace;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/workspace-'.bin2hex(random_bytes(4));

    File::ensureDirectoryExists($this->path);
    File::put("{$this->path}/.env", implode("\n", [
        'APP_NAME=Acme',
        'APP_URL=https://acme.test',
        'APP_KEY=base64:secret',
        'DB_PASSWORD=secret',
        'PODMAN_WORKING_PATH=/home/ada/acme',
        'PODMAN_DEFAULT_PRESETS=production',
    ]));

    $this->workspace = new Workspace($this->path, "{$this->path}/.env");
});

afterEach(function () {
    File::deleteDirectory($this->path);
});

it('only reads the app and Podman values from the env file', function () {
    expect($this->workspace->environment())->toBe([
        'APP_NAME' => 'Acme',
        'APP_URL' => 'https://acme.test',
        'PODMAN_WORKING_PATH' => '/home/ada/acme',
        'PODMAN_DEFAULT_PRESETS' => 'production',
    ]);
});

it('renders the app\'s presets into the workspace with its own values', function () {
    $folders = $this->workspace->render();

    expect($folders)->toBe(["{$this->path}/podman/production"])
        ->and(File::get("{$this->path}/podman/production/app.quadlets"))
        ->toContain('# FileName=acme')
        ->toContain('/home/ada/acme/podman/production/runtimes');
});

it('restores the installer\'s own config afterwards', function () {
    $name = config('app.name');

    $this->workspace->render();

    expect(config('app.name'))->toBe($name)
        ->and(config('podman.base_path'))->toBeNull()
        ->and(env('PODMAN_WORKING_PATH'))->toBeNull();
});

it('lets the installer\'s own Podman environment win over the env file', function () {
    $_SERVER['PODMAN_WORKING_PATH'] = '/srv/acme';

    try {
        expect($this->workspace->describe())->toMatchArray(['name' => 'Acme', 'path' => '/srv/acme', 'presets' => ['production']]);
    } finally {
        unset($_SERVER['PODMAN_WORKING_PATH']);
    }
});

it('needs a mounted workspace', function () {
    (new Workspace(null, null))->render();
})->throws(ServicesException::class, 'No workspace is mounted');

it('needs presets to render', function () {
    File::put("{$this->path}/.env", "APP_NAME=Acme\nPODMAN_DEFAULT_PRESETS=");

    $this->workspace->render();
})->throws(ServicesException::class, 'no presets');
