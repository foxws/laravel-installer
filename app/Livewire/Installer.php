<?php

namespace App\Livewire;

use App\Installer\DoctorReport;
use App\Installer\Manifest;
use App\Installer\Step;
use App\Installer\StepType;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Installer extends Component
{
    /**
     * Locked, so the browser can't skip a step, such as the requirements.
     */
    #[Locked]
    public int $current = 0;

    #[Computed]
    public function manifest(): Manifest
    {
        return app(Manifest::class);
    }

    #[Computed]
    public function step(): Step
    {
        return $this->manifest->steps[$this->current];
    }

    #[Computed]
    public function report(): ?DoctorReport
    {
        return DoctorReport::fromFile(config('installer.doctor_report'));
    }

    /**
     * Whether the current step lets the installer move on. The requirements
     * step holds it back while the host report is missing or has failures.
     */
    #[Computed]
    public function canContinue(): bool
    {
        if ($this->step->type !== StepType::Requirements) {
            return true;
        }

        return $this->report !== null && ! $this->report->hasFailures();
    }

    public function next(): void
    {
        if (! $this->canContinue || $this->current >= count($this->manifest->steps) - 1) {
            return;
        }

        $this->current++;

        unset($this->step, $this->canContinue);
    }

    public function previous(): void
    {
        $this->current = max(0, $this->current - 1);

        unset($this->step, $this->canContinue);
    }

    public function render(): View
    {
        return view('livewire.installer')
            ->title("{$this->step->title} · {$this->manifest->name}");
    }
}
