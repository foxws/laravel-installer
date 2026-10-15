<?php

namespace App\Livewire;

use App\Installer\CommandRun;
use App\Installer\DoctorReport;
use App\Installer\Input;
use App\Installer\Manifest;
use App\Installer\Podman;
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
     * step holds it back while the host report is missing or has failures,
     * and a command step until its command ran.
     */
    #[Computed]
    public function canContinue(): bool
    {
        return match ($this->step->type) {
            StepType::Content => true,
            StepType::Requirements => $this->report !== null && ! $this->report->hasFailures(),
            StepType::Command => in_array($this->step->id, $this->completed, true),
        };
    }

    public function runCommand(Podman $podman): void
    {
        $step = $this->step;

        if ($step->run === null || in_array($step->id, $this->completed, true)) {
            return;
        }

        $this->validate(
            collect($step->inputs)->mapWithKeys(fn (Input $input): array => ["values.{$input->name}" => $input->rules])->all(),
            attributes: collect($step->inputs)->mapWithKeys(fn (Input $input): array => ["values.{$input->name}" => $input->label])->all(),
        );

        $values = collect($step->inputs)->mapWithKeys(fn (Input $input): array => [$input->name => (string) ($this->values[$input->name] ?? '')])->all();
        $run = $step->run;
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

        $output = $this->hideSecrets($step, $values, trim($result->output()."\n".$result->errorOutput()));

        if ($result->successful()) {
            $this->complete($step, $output);

            return;
        }

        $this->clearSecrets($step);
        $this->output = $output;
        $this->addError('command', "The command failed with exit code {$result->exitCode()}.");
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

    public function render(): View
    {
        return view('livewire.installer')
            ->title("{$this->step->title} · {$this->manifest->name}");
    }

    protected function complete(Step $step, string $output): void
    {
        $this->completed[] = $step->id;
        $this->output = $output;
        $this->clearSecrets($step);

        unset($this->canContinue);
    }

    protected function resetStep(): void
    {
        $this->values = [];
        $this->output = null;
        $this->resetErrorBag();

        unset($this->step, $this->canContinue);
    }

    protected function clearSecrets(Step $step): void
    {
        foreach ($step->secretInputs() as $input) {
            unset($this->values[$input->name]);
        }
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
