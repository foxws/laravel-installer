<?php

use App\Installer\QuadletInstaller;
use App\Installer\ServicesException;
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
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('writes each unit of a ".quadlets" file to its own file', function () {
    $this->installer->install(["{$this->root}/rendered"]);

    expect(File::get("{$this->root}/quadlets/acme.build"))->toBe("# FileName=acme\n[Build]\nImageTag=localhost/acme:latest\n")
        ->and(File::get("{$this->root}/quadlets/acme.container"))->toContain("[Container]\nImage=acme.build")
        ->and(File::exists("{$this->root}/quadlets/acme-data.volume"))->toBeTrue();
});

it('copies plain systemd units to the user folder', function () {
    $this->installer->install(["{$this->root}/rendered"]);

    expect(File::exists("{$this->root}/units/acme-schedule.timer"))->toBeTrue()
        ->and(File::exists("{$this->root}/units/acme-schedule.service"))->toBeTrue();
});

it('returns the services to start and follow', function () {
    $units = $this->installer->install(["{$this->root}/rendered"]);

    expect($units->services)->toBe(['acme-build.service', 'acme.service', 'acme-data-volume.service'])
        ->and($units->start)->toBe(['acme.service'])
        ->and($units->enable)->toBe(['acme-schedule.timer']);
});

it('refuses units without a file name or Quadlet section', function (string $contents) {
    File::put("{$this->root}/rendered/app.quadlets", $contents);

    $this->installer->install(["{$this->root}/rendered"]);
})->with([
    'no file name' => "[Container]\nImage=acme\n",
    'no Quadlet section' => "# FileName=acme\n[Unit]\nDescription=Acme\n",
    'a path as file name' => "# FileName=../acme\n[Container]\nImage=acme\n",
])->throws(ServicesException::class);
