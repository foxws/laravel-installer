<?php

declare(strict_types=1);

namespace App\Installer;

readonly class RequirementCheck
{
    public function __construct(
        public string $id,
        public CheckStatus $status,
        public string $message,
        public string $fix = '',
    ) {}
}
