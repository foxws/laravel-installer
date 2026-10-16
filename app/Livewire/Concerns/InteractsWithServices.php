<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Installer\QuadletInstaller;
use App\Installer\ServicesException;
use App\Installer\Systemd;
use App\Installer\Workspace;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Renders the app's presets into the workspace, installs them as Quadlets
 * and starts them, then follows their state until they're all running.
 */
trait InteractsWithServices
{
    /**
     * The services each services step started, by step id.
     *
     * @var array<string, list<string>>
     */
    #[Locked]
    public array $services = [];

    /**
     * What the current services step will install, or null when there's no
     * workspace to install from.
     *
     * @return array{name: string, url: string, path: string, presets: list<string>}|null
     */
    #[Computed]
    public function workspace(): ?array
    {
        $workspace = Workspace::fromConfig();

        return $workspace->isAvailable() ? $workspace->describe($this->step->presets) : null;
    }

    /**
     * The state of each service the current step started, such as "active"
     * or "failed".
     *
     * @return array<string, string>
     */
    #[Computed]
    public function serviceStates(): array
    {
        $systemd = app(Systemd::class);

        return collect($this->services[$this->step->id] ?? [])
            ->mapWithKeys(function (string $service) use ($systemd): array {
                try {
                    return [$service => $systemd->activeState($service)];
                } catch (ServicesException) {
                    return [$service => 'unknown'];
                }
            })
            ->all();
    }

    public function installServices(Systemd $systemd): void
    {
        $step = $this->step;

        if ($this->servicesAreRunning()) {
            return;
        }

        try {
            $units = QuadletInstaller::fromConfig()->install(Workspace::fromConfig()->render($step->presets));

            $systemd->reload();
            $systemd->enable($units->enable);

            foreach ([...$units->start, ...$units->enable] as $unit) {
                $systemd->start($unit);
            }
        } catch (ServicesException $exception) {
            $this->addError('services', $exception->getMessage());

            return;
        }

        $this->services[$step->id] = $units->services;

        unset($this->serviceStates, $this->canContinue);
    }

    /**
     * Called by polling while the services start.
     */
    public function refreshServices(): void
    {
        unset($this->serviceStates, $this->canContinue);
    }

    /**
     * Whether the current step started its services, and they all run.
     */
    protected function servicesAreRunning(): bool
    {
        return $this->serviceStates !== [] && collect($this->serviceStates)->every(fn (string $state): bool => $state === 'active');
    }
}
