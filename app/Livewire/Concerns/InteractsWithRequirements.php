<?php

namespace App\Livewire\Concerns;

use App\Installer\DoctorReport;
use Livewire\Attributes\Computed;

/**
 * The host checks from "lpod doctor --json", which hold the installer back
 * while the report is missing or has failures.
 */
trait InteractsWithRequirements
{
    #[Computed]
    public function report(): ?DoctorReport
    {
        return DoctorReport::fromFile(config('installer.doctor_report'));
    }

    protected function passesRequirements(): bool
    {
        return $this->report !== null && ! $this->report->hasFailures();
    }
}
