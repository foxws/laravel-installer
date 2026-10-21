<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Installer\InstallerMode;
use App\Installer\Source;
use App\Livewire\Concerns\InteractsWithCommands;
use App\Livewire\Concerns\InteractsWithRequirements;
use App\Livewire\Concerns\InteractsWithSecrets;
use App\Livewire\Concerns\InteractsWithServices;
use App\Livewire\Concerns\InteractsWithSteps;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Installer extends Component
{
    use InteractsWithCommands;
    use InteractsWithRequirements;
    use InteractsWithSecrets;
    use InteractsWithServices;
    use InteractsWithSteps;

    public function mount(): void
    {
        if (InstallerMode::needsSource()) {
            $this->redirectRoute('source');
        }
    }

    /**
     * The repository and version the app was checked out from, unless the
     * installer came with its own installer folder.
     */
    #[Computed]
    public function source(): ?Source
    {
        return InstallerMode::isBaked() ? null : Source::current();
    }

    /**
     * The version the installer installs: the manifest's, or else the tag
     * that was checked out.
     */
    #[Computed]
    public function version(): ?string
    {
        return $this->manifest->version ?? $this->source?->version();
    }

    /**
     * Whether another source can still be chosen: only until a step changed
     * something on the host.
     */
    #[Computed]
    public function canChangeSource(): bool
    {
        return $this->source !== null && $this->completed === [] && $this->services === [];
    }

    public function render(): View
    {
        return view('livewire.installer')
            ->title("{$this->step->title} · {$this->manifest->name}");
    }
}
