<?php

namespace App\Installer;

use RuntimeException;

class ServicesException extends RuntimeException
{
    public static function noWorkspace(): self
    {
        return new self('No workspace is mounted. Start the installer with "lpod installer IMAGE --workspace PATH".');
    }

    public static function noPresets(): self
    {
        return new self('There are no presets to install. Set PODMAN_DEFAULT_PRESETS in the app\'s .env file, or list "presets" in the step.');
    }

    public static function invalidQuadlets(string $file): self
    {
        return new self("{$file} has a unit without a \"# FileName=\" line or a known Quadlet section.");
    }

    public static function systemd(string $action, string $error): self
    {
        return new self("Systemd couldn't {$action}: {$error}");
    }
}
