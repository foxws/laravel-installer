<?php

declare(strict_types=1);

namespace App\Installer;

use RuntimeException;

class SourceException extends RuntimeException
{
    public static function invalidUrl(): self
    {
        return new self('The repository must be an https:// address, such as https://github.com/example/app.git.');
    }

    public static function git(string $action, string $error): self
    {
        return new self(trim("Git couldn't {$action}: {$error}"));
    }

    public static function noWorkspace(string $path): self
    {
        return new self("No workspace is mounted at {$path}, so there's nowhere to install the app. Start the installer with the install location mounted there.");
    }

    public static function notEmpty(string $path): self
    {
        return new self("The workspace at {$path} isn't empty, and isn't a checkout of this repository. Mount an empty folder to install the app in.");
    }
}
