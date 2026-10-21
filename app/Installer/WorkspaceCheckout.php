<?php

declare(strict_types=1);

namespace App\Installer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Whether the app can be checked out into the workspace: it must be empty,
 * or a checkout of the same repository from an earlier run, so the
 * installer never writes over files of something else.
 */
class WorkspaceCheckout
{
    public function __construct(
        protected Git $git,
    ) {}

    public function ensureCanReceive(Workspace $workspace, string $url): void
    {
        if (! $workspace->isAvailable()) {
            throw SourceException::noWorkspace((string) $workspace->path);
        }

        $path = (string) $workspace->path;

        if (File::isEmptyDirectory($path)) {
            return;
        }

        $origin = is_dir("{$path}/.git") ? $this->git->origin($path) : null;

        if ($origin === null || static::normalize($origin) !== static::normalize($url)) {
            throw SourceException::notEmpty($path);
        }
    }

    protected static function normalize(string $url): string
    {
        return Str::of($url)->lower()->rtrim('/')->chopEnd('.git')->toString();
    }
}
