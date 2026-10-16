<?php

namespace App\Livewire\Concerns;

use App\Installer\Manifest;
use App\Installer\Step;
use App\Installer\StepType;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Moves through the manifest's steps, one at a time, and only on once the
 * current step allows it.
 */
trait InteractsWithSteps
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

    /**
     * Whether the current step lets the installer move on.
     */
    #[Computed]
    public function canContinue(): bool
    {
        return match ($this->step->type) {
            StepType::Content => true,
            StepType::Requirements => $this->passesRequirements(),
            StepType::Command => $this->hasCompleted($this->step),
            StepType::Services => $this->servicesAreRunning(),
        };
    }

    public function next(): void
    {
        if (! $this->canContinue || $this->current >= count($this->manifest->steps) - 1) {
            return;
        }

        $this->current++;
        $this->resetStep();
    }

    public function previous(): void
    {
        $this->current = max(0, $this->current - 1);
        $this->resetStep();
    }

    protected function resetStep(): void
    {
        $this->resetCommand();

        unset($this->step, $this->canContinue, $this->workspace, $this->serviceStates);
    }
}
