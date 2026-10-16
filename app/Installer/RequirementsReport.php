<?php

declare(strict_types=1);

namespace App\Installer;

/**
 * The host checks of a requirements step.
 */
readonly class RequirementsReport
{
    /**
     * @param  list<RequirementCheck>  $checks
     */
    public function __construct(
        public array $checks,
    ) {}

    public function hasFailures(): bool
    {
        return $this->count(CheckStatus::Fail) > 0;
    }

    public function count(CheckStatus $status): int
    {
        return count(array_filter($this->checks, fn (RequirementCheck $check): bool => $check->status === $status));
    }
}
