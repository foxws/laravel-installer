<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Installer\ExistingInstallCheck;
use App\Installer\Podman;
use App\Installer\PodmanInfoReport;
use App\Installer\RequirementsReport;
use App\Installer\Workspace;
use Livewire\Attributes\Computed;

/**
 * The host checks, which hold the installer back while one of them fails.
 * They come from what the installer can see through the Podman socket, and
 * whether the app is installed already.
 */
trait InteractsWithRequirements
{
    #[Computed]
    public function report(): RequirementsReport
    {
        $report = PodmanInfoReport::fromInfo(app(Podman::class)->info());

        // Once this run installed the services, they're its own, so going
        // back to the requirements mustn't stop it...
        $existing = $this->services === [] ? ExistingInstallCheck::forWorkspace(Workspace::fromConfig()) : null;

        return $existing === null ? $report : new RequirementsReport([...$report->checks, $existing]);
    }

    protected function passesRequirements(): bool
    {
        return ! $this->report->hasFailures();
    }
}
