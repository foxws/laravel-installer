<?php

declare(strict_types=1);

namespace App\Installer;

enum CheckStatus: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Fail = 'fail';
    case Unknown = 'unknown';

    public function alertType(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Warn => 'warning',
            self::Fail => 'error',
            self::Unknown => 'info',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Warn => 'Warning',
            self::Fail => 'Failed',
            self::Unknown => 'Unknown',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Ok => '✓',
            self::Warn => '!',
            self::Fail => '✗',
            self::Unknown => '?',
        };
    }
}
