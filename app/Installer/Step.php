<?php

declare(strict_types=1);

namespace App\Installer;

readonly class Step
{
    /**
     * @param  list<Input>  $inputs
     * @param  list<string>  $presets  The presets a services step installs, or the app's default ones when empty.
     */
    public function __construct(
        public string $id,
        public StepType $type,
        public string $title,
        public string $html,
        public array $inputs = [],
        public ?CommandRun $run = null,
        public array $presets = [],
    ) {}

    /**
     * @return list<Input>
     */
    public function secretInputs(): array
    {
        return array_values(array_filter($this->inputs, fn (Input $input): bool => $input->secret));
    }
}
