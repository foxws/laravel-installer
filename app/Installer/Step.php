<?php

namespace App\Installer;

readonly class Step
{
    public function __construct(
        public string $id,
        public StepType $type,
        public string $title,
        public string $html,
    ) {}
}
