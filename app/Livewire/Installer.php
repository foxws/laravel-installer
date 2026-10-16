<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithCommands;
use App\Livewire\Concerns\InteractsWithRequirements;
use App\Livewire\Concerns\InteractsWithSecrets;
use App\Livewire\Concerns\InteractsWithServices;
use App\Livewire\Concerns\InteractsWithSteps;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Installer extends Component
{
    use InteractsWithCommands;
    use InteractsWithRequirements;
    use InteractsWithSecrets;
    use InteractsWithServices;
    use InteractsWithSteps;

    public function render(): View
    {
        return view('livewire.installer')
            ->title("{$this->step->title} · {$this->manifest->name}");
    }
}
