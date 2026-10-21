<?php

declare(strict_types=1);

namespace App\Installer;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Where the installer's manifest comes from. An image built on top of the
 * installer has its own installer folder, which it starts with right away.
 * Without one, the installer first asks for the app's git repository and
 * reads the manifest from the version it checked out into the workspace.
 */
class InstallerMode
{
    public static function isBaked(): bool
    {
        return is_file(Config::string('installer.path').'/manifest.yml');
    }

    public static function needsSource(): bool
    {
        return ! static::isBaked() && Source::current() === null;
    }

    /**
     * The folder in the workspace that holds a checked out app's manifest.
     */
    public static function sourcePath(): string
    {
        $workspace = Str::rtrim((string) Workspace::fromConfig()->path, '/');

        return "{$workspace}/".Str::trim(Config::string('installer.source_folder'), '/');
    }

    public static function manifestPath(): string
    {
        return static::isBaked() ? Config::string('installer.path') : static::sourcePath();
    }
}
