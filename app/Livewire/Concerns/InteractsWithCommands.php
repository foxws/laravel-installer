<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Installer\CommandRun;
use App\Installer\Input;
use App\Installer\Podman;
use App\Installer\ServicesException;
use App\Installer\Step;
use App\Installer\Workspace;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Asks for a command step's inputs and runs its command in the app's
 * container. Secret values never stay around longer than the run.
 */
trait InteractsWithCommands
{
    /**
     * The ids of the command steps that ran, or were already done.
     *
     * @var list<string>
     */
    #[Locked]
    public array $completed = [];

    /**
     * The values of the current step's inputs. Secret values are cleared as
     * soon as the command ran.
     *
     * @var array<string, string>
     */
    public array $values = [];

    /**
     * The output of the last command, with secret values hidden.
     */
    #[Locked]
    public ?string $output = null;

    public function runCommand(Podman $podman): void
    {
        $step = $this->step;

        if ($step->run === null || $this->hasCompleted($step)) {
            return;
        }

        // Livewire falls back to the component's own rules when given none,
        // so a step without inputs has nothing to validate...
        if ($step->inputs !== []) {
            $this->validate(
                collect($step->inputs)->mapWithKeys(fn (Input $input): array => ["values.{$input->name}" => $input->rules])->all(),
                attributes: collect($step->inputs)->mapWithKeys(fn (Input $input): array => ["values.{$input->name}" => $input->label])->all(),
            );
        }

        $values = collect($step->inputs)->mapWithKeys(fn (Input $input): array => [$input->name => (string) ($this->values[$input->name] ?? '')])->all();
        $run = $step->run;

        try {
            $values = [...$this->placeholders(), ...$values];
        } catch (ServicesException $exception) {
            $this->addError('command', $exception->getMessage());

            return;
        }
        $container = CommandRun::fillString($run->container, $values);

        if ($run->skipIf !== null && $podman->exec($container, CommandRun::fill($run->skipIf, $values))->successful()) {
            $this->complete($step, 'This was already done, so the installer skipped it.');

            return;
        }

        $result = $podman->exec(
            container: $container,
            command: CommandRun::fill($run->command, $values),
            stdin: $run->stdin !== null ? CommandRun::fillString($run->stdin, $values) : null,
            env: array_map(fn (string $value): string => CommandRun::fillString($value, $values), $run->env),
        );

        $output = $this->hideSecrets($step, $values, $this->plainOutput($result->output()."\n".$result->errorOutput()));

        if ($result->successful()) {
            $this->complete($step, $output);

            return;
        }

        $this->clearSecrets($step);
        $this->output = $output;
        $this->addError('command', "The command failed with exit code {$result->exitCode()}.");
    }

    /**
     * The values every command step can use besides its inputs:
     * "{application}", the app's quadlet prefix from the workspace, so a
     * container can be named "systemd-{application}".
     *
     * @return array<string, string>
     */
    protected function placeholders(): array
    {
        $workspace = Workspace::fromConfig();

        return $workspace->isAvailable() ? ['application' => $workspace->application()] : [];
    }

    protected function hasCompleted(Step $step): bool
    {
        return in_array($step->id, $this->completed, true);
    }

    protected function complete(Step $step, string $output): void
    {
        $this->completed[] = $step->id;
        $this->output = $output;
        $this->clearSecrets($step);

        unset($this->canContinue);
    }

    protected function resetCommand(): void
    {
        $this->values = [];
        $this->output = null;
        $this->resetErrorBag();
    }

    protected function clearSecrets(Step $step): void
    {
        foreach ($step->secretInputs() as $input) {
            unset($this->values[$input->name]);
        }
    }

    /**
     * The output without terminal escape sequences, such as the colors
     * Laravel Prompts adds even when there's no terminal.
     */
    protected function plainOutput(string $output): string
    {
        return Str::of($output)
            ->replaceMatches('/\e\[[0-9;?]*[ -\/]*[@-~]|\e\][^\a\e]*(?:\a|\e\\\\)/', '')
            ->trim()
            ->value();
    }

    /**
     * @param  array<string, string>  $values
     */
    protected function hideSecrets(Step $step, array $values, string $output): string
    {
        foreach ($step->secretInputs() as $input) {
            if ($values[$input->name] !== '') {
                $output = str_replace($values[$input->name], '••••••••', $output);
            }
        }

        return $output;
    }
}
