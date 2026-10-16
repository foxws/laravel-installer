<?php

declare(strict_types=1);

namespace App\Installer;

use Foxws\Podman\Enums\QuadletSecretType;
use Foxws\Podman\Support\PodmanQuadletSecret;
use Foxws\Podman\Support\PodmanRenderedPreset;
use Illuminate\Support\Collection;

/**
 * A Podman secret the app's presets use, and where the installer takes its
 * value from: a key of the app's ".env", a file in the workspace, or what
 * the person types in, or else a generated value.
 */
readonly class RequiredSecret
{
    /**
     * @param  list<string>  $targets  Where the containers get it, such as "POSTGRES_PASSWORD" or "/app/.env".
     */
    public function __construct(
        public string $name,
        public QuadletSecretType $type,
        public array $targets,
        public ?string $envKey = null,
        public ?string $file = null,
    ) {}

    /**
     * The secrets of the rendered presets, once per name, with the sources
     * the step maps their targets to. Mounted secrets without a mapped file
     * come from the app's ".env", as with "lpod SERVICE secrets".
     *
     * @param  list<PodmanRenderedPreset>  $presets
     * @return list<self>
     */
    public static function forStep(Step $step, array $presets, string $envFile): array
    {
        return array_values(Collection::make($presets)
            ->flatMap(fn (PodmanRenderedPreset $preset): array => $preset->secrets())
            ->groupBy(fn (PodmanQuadletSecret $secret): string => $secret->name)
            ->map(function (Collection $secrets, string $name) use ($step, $envFile): self {
                /** @var PodmanQuadletSecret $first */
                $first = $secrets->first();
                $targets = $secrets->map(fn (PodmanQuadletSecret $secret): string => $secret->target)->unique()->values()->all();

                if ($first->type === QuadletSecretType::Mount) {
                    $file = collect($targets)->map(fn (string $target): ?string => $step->secretFiles[$target] ?? null)->filter()->first();

                    return new self($name, $first->type, $targets, file: $file ?? $envFile);
                }

                $envKey = collect($targets)->map(fn (string $target): ?string => $step->secretValues[$target] ?? null)->filter()->first();

                return new self($name, $first->type, $targets, envKey: $envKey);
            })
            ->all());
    }

    /**
     * Whether the person has to give the value, or may leave it empty to
     * generate one.
     */
    public function needsInput(): bool
    {
        return $this->type === QuadletSecretType::Env && $this->envKey === null;
    }
}
