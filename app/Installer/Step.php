<?php

namespace App\Installer;

readonly class Step
{
    /**
     * @param  list<Input>  $inputs
     */
    public function __construct(
        public string $id,
        public StepType $type,
        public string $title,
        public string $html,
        public array $inputs = [],
        public ?CommandRun $run = null,
    ) {}

    /**
     * @return list<Input>
     */
    public function secretInputs(): array
    {
        return array_values(array_filter($this->inputs, fn (Input $input): bool => $input->secret));
    }
}
