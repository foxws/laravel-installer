<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Installer\DoctorReport;
use App\Installer\Podman;
use App\Installer\PodmanInfoReport;
use Illuminate\Support\Facades\Config;
use Livewire\Attributes\Computed;

/**
 * The host checks, which hold the installer back while one of them fails.
 * They come from "lpod doctor --json" when it ran on the host first, or
 * else from what the installer can see through the Podman socket.
 */
trait InteractsWithRequirements
{
    #[Computed]
    public function report(): DoctorReport
    {
        return DoctorReport::fromFile(Config::string('installer.doctor_report'))
            ?? PodmanInfoReport::fromInfo(app(Podman::class)->info());
    }

    protected function passesRequirements(): bool
    {
        return ! $this->report->hasFailures();
    }
}
