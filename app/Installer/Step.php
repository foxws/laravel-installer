<?php

declare(strict_types=1);

namespace App\Installer;

readonly class Step
{
    /**
     * @param  list<Input>  $inputs
     * @param  list<string>  $presets  The presets a services or secrets step installs, or the app's default ones when empty.
     * @param  array<string, string>  $secretValues  For a secrets step: the ".env" key to read each "type=env" secret from, by target.
     * @param  array<string, string>  $secretFiles  For a secrets step: the workspace file to read each mounted secret from, by target.
     */
    public function __construct(
        public string $id,
        public StepType $type,
        public string $title,
        public string $html,
        public array $inputs = [],
        public ?CommandRun $run = null,
        public array $presets = [],
        public array $secretValues = [],
        public array $secretFiles = [],
    ) {}

    /**
     * @return list<Input>
     */
    public function secretInputs(): array
    {
        return array_values(array_filter($this->inputs, fn (Input $input): bool => $input->secret));
    }
}
