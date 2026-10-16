<?php

declare(strict_types=1);

use App\Installer\QuadletInstaller;
use App\Installer\ServicesException;
use Foxws\Podman\Support\PodmanRenderedPreset;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/quadlets-'.bin2hex(random_bytes(4));

    File::ensureDirectoryExists("{$this->root}/rendered");
    File::put("{$this->root}/rendered/app.quadlets", <<<'QUADLETS'
        # FileName=acme
        [Build]
        ImageTag=localhost/acme:latest
        ---
        # FileName=acme
        [Unit]
        Description=Acme

        [Container]
        Image=acme.build
        ---
        # FileName=acme-data
        [Volume]
        VolumeName=systemd-acme-data
        QUADLETS);
    File::put("{$this->root}/rendered/acme-schedule.timer", "[Timer]\nOnCalendar=minutely\n");
    File::put("{$this->root}/rendered/acme-schedule.service", "[Service]\nType=oneshot\n");

    $this->installer = new QuadletInstaller("{$this->root}/quadlets", "{$this->root}/units");

    $this->rendered = new PodmanRenderedPreset(
        preset: 'production',
        path: "{$this->root}/rendered",
        quadlets: ["{$this->root}/rendered/app.quadlets"],
        systemdUnits: ["{$this->root}/rendered/acme-schedule.timer", "{$this->root}/rendered/acme-schedule.service"],
        runtimesPath: null,
    );
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('writes each unit of a ".quadlets" file to its own file', function () {
    $this->installer->install([$this->rendered]);

    expect(File::get("{$this->root}/quadlets/acme.build"))->toBe("# FileName=acme\n[Build]\nImageTag=localhost/acme:latest\n")
        ->and(File::get("{$this->root}/quadlets/acme.container"))->toContain("[Container]\nImage=acme.build")
        ->and(File::exists("{$this->root}/quadlets/acme-data.volume"))->toBeTrue();
});

it('copies plain systemd units to the user folder', function () {
    $this->installer->install([$this->rendered]);

    expect(File::exists("{$this->root}/units/acme-schedule.timer"))->toBeTrue()
        ->and(File::exists("{$this->root}/units/acme-schedule.service"))->toBeTrue();
});

it('returns the services to start and follow', function () {
    $units = $this->installer->install([$this->rendered]);

    expect($units->start)->toBe(['acme.service'])
        ->and($units->enable)->toBe(['acme-schedule.timer'])
        ->and($units->services)->toBe(['acme.service', 'acme-schedule.timer']);
});

it('leaves containers a timer starts to their timer', function () {
    File::append("{$this->root}/rendered/app.quadlets", "\n---\n# FileName=acme-schedule\n[Container]\nImage=acme.build\n");

    $units = $this->installer->install([$this->rendered]);

    expect($units->start)->toBe(['acme.service'])
        ->and($units->enable)->toBe(['acme-schedule.timer']);
});

it('wakes the on-demand socket instead of starting anything', function () {
    File::put("{$this->root}/rendered/acme-ondemand.socket", "[Socket]\nListenStream=8000\n");
    File::put("{$this->root}/rendered/acme-ondemand.service", "[Service]\nExecStart=/usr/lib/systemd/systemd-socket-proxyd 127.0.0.1:18000\n");

    $rendered = new PodmanRenderedPreset(
        preset: 'production',
        path: "{$this->root}/rendered",
        quadlets: ["{$this->root}/rendered/app.quadlets"],
        systemdUnits: ["{$this->root}/rendered/acme-ondemand.socket", "{$this->root}/rendered/acme-ondemand.service", "{$this->root}/rendered/acme-schedule.timer"],
        runtimesPath: null,
    );

    $units = $this->installer->install([$rendered]);

    expect($units->start)->toBe([])
        ->and($units->enable)->toBe(['acme-ondemand.socket', 'acme-schedule.timer'])
        ->and($units->wake)->toBe(['127.0.0.1:8000'])
        ->and($units->services)->toBe(['acme-ondemand.service', 'acme-ondemand.socket', 'acme-schedule.timer'])
        ->and(File::exists("{$this->root}/units/acme-ondemand.service"))->toBeTrue();
});

it('refuses rendered units that are not valid', function (string $contents) {
    File::put("{$this->root}/rendered/app.quadlets", $contents);

    $this->installer->install([$this->rendered]);
})->with([
    'no file name' => "[Container]\nImage=acme\n",
    'no Quadlet section' => "# FileName=acme\n[Unit]\nDescription=Acme\n",
    'a path as file name' => "# FileName=../acme\n[Container]\nImage=acme\n",
])->throws(ServicesException::class, "The rendered services can't be installed");

it('reads the address to wake a socket on', function (string $listen, ?string $address) {
    File::put("{$this->root}/acme.socket", "[Socket]\nListenStream={$listen}\n");

    expect($this->installer->listenAddress("{$this->root}/acme.socket"))->toBe($address);
})->with([
    'a port' => ['8001', '127.0.0.1:8001'],
    'every IPv4 address' => ['0.0.0.0:8001', '127.0.0.1:8001'],
    'every IPv6 address' => ['[::]:8001', '127.0.0.1:8001'],
    'one address' => ['192.168.1.100:8001', '192.168.1.100:8001'],
    'a file' => ['/run/acme.sock', null],
]);
