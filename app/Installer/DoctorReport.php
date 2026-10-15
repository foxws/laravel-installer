<?php

namespace App\Installer;

use JsonException;

/**
 * The host checks from "lpod doctor --json". It runs on the host before the
 * installer starts, since the installer can't look at the host from inside
 * its container.
 */
readonly class DoctorReport
{
    /**
     * @param  list<DoctorCheck>  $checks
     */
    public function __construct(
        public string $lpodVersion,
        public array $checks,
    ) {}

    /**
     * The report at the given path, or null when it's missing or isn't a
     * report "lpod doctor --json" wrote.
     */
    public static function fromFile(string $path): ?self
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $report = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($report) || ! is_array($report['checks'] ?? null)) {
            return null;
        }

        $checks = [];

        foreach ($report['checks'] as $check) {
            $status = is_array($check) ? CheckStatus::tryFrom((string) ($check['status'] ?? '')) : null;

            if ($status === null || ! is_string($check['id'] ?? null) || ! is_string($check['message'] ?? null)) {
                return null;
            }

            $checks[] = new DoctorCheck(
                id: $check['id'],
                status: $status,
                message: $check['message'],
                fix: is_string($check['fix'] ?? null) ? $check['fix'] : '',
            );
        }

        return new self((string) ($report['lpod'] ?? ''), $checks);
    }

    public function hasFailures(): bool
    {
        return $this->count(CheckStatus::Fail) > 0;
    }

    public function count(CheckStatus $status): int
    {
        return count(array_filter($this->checks, fn (DoctorCheck $check): bool => $check->status === $status));
    }
}
