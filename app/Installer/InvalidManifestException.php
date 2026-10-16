<?php

declare(strict_types=1);

namespace App\Installer;

use RuntimeException;

class InvalidManifestException extends RuntimeException
{
    public static function missing(string $path): self
    {
        return new self("No installer manifest found at {$path}.");
    }

    public static function invalid(string $path, string $reason): self
    {
        return new self("The installer manifest at {$path} is invalid: {$reason}");
    }
}
