<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Installer\Podman;
use App\Installer\PodmanInfoReport;
use App\Installer\RequirementsReport;
use Livewire\Attributes\Computed;

/**
 * The host checks, which hold the installer back while one of them fails.
 * They come from what the installer can see through the Podman socket.
 */
trait InteractsWithRequirements
{
    #[Computed]
    public function report(): RequirementsReport
    {
        return PodmanInfoReport::fromInfo(app(Podman::class)->info());
    }

    protected function passesRequirements(): bool
    {
        return ! $this->report->hasFailures();
    }
}
