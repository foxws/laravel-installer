<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Installer\RequiredSecret;
use App\Installer\ServicesException;
use App\Installer\Workspace;
use Foxws\Podman\Enums\QuadletSecretType;
use Foxws\Podman\Support\PodmanSecrets;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use RuntimeException;

/**
 * Creates the Podman secrets the app's presets use, with "podman secret"
 * through the socket. Values come from the app's ".env", files in the
 * workspace, or the inputs, and never stay around longer than the run.
 */
trait InteractsWithSecrets
{
    /**
     * The values typed in for secrets without another source, by name.
     * Cleared as soon as the secrets were created.
     *
     * @var array<string, string>
     */
    public array $secretValues = [];

    /**
     * Whether to replace secrets that exist already.
     */
    public bool $replaceSecrets = false;

    /**
     * The secrets the current step's presets use, and whether each one
     * exists, or null when there's no workspace to read them from.
     *
     * @return list<array{secret: RequiredSecret, exists: bool, source: string, input: bool}>|null
     */
    #[Computed]
    public function requiredSecrets(): ?array
    {
        $workspace = Workspace::fromConfig();

        if (! $workspace->isAvailable()) {
            return null;
        }

        try {
            $secrets = RequiredSecret::forStep($this->step, $workspace->render($this->step->presets), (string) $workspace->envFile);
            $existing = app(PodmanSecrets::class)->names();
        } catch (ServicesException|RuntimeException $exception) {
            $this->addError('secrets', $exception->getMessage());

            return [];
        }

        return array_map(fn (RequiredSecret $secret): array => [
            'secret' => $secret,
            'exists' => in_array($secret->name, $existing, true),
            'source' => $this->secretSource($secret, $workspace),
            'input' => $secret->type === QuadletSecretType::Env && ($secret->envKey === null || $workspace->value($secret->envKey) === null),
        ], $secrets);
    }

    public function createSecrets(PodmanSecrets $podmanSecrets): void
    {
        $workspace = Workspace::fromConfig();

        try {
            foreach ($this->requiredSecrets ?? [] as ['secret' => $secret, 'exists' => $exists]) {
                if ($exists && ! $this->replaceSecrets) {
                    continue;
                }

                if ($secret->type === QuadletSecretType::Mount) {
                    $podmanSecrets->createFromFile($secret->name, $workspace->file((string) $secret->file));

                    continue;
                }

                $value = ($secret->envKey !== null ? $workspace->value($secret->envKey) : null)
                    ?? (($this->secretValues[$secret->name] ?? '') !== '' ? $this->secretValues[$secret->name] : Str::random(40));

                $podmanSecrets->create($secret->name, $value);
            }
        } catch (RuntimeException $exception) {
            $this->addError('secrets', $exception->getMessage());

            return;
        } finally {
            $this->secretValues = [];
        }

        $this->replaceSecrets = false;

        unset($this->requiredSecrets, $this->canContinue);
    }

    /**
     * Whether every secret the current step's presets use exists.
     */
    protected function secretsExist(): bool
    {
        return $this->requiredSecrets !== null
            && ! $this->getErrorBag()->has('secrets')
            && collect($this->requiredSecrets)->every(fn (array $required): bool => $required['exists']);
    }

    /**
     * Where the secret's value comes from, to show before creating it.
     */
    protected function secretSource(RequiredSecret $secret, Workspace $workspace): string
    {
        if ($secret->type === QuadletSecretType::Mount) {
            return 'The file '.Str::after((string) $secret->file, "{$workspace->path}/");
        }

        if ($secret->envKey !== null && $workspace->value($secret->envKey) !== null) {
            return "{$secret->envKey} in the app's .env";
        }

        return 'Typed in below, or generated when left empty';
    }
}
