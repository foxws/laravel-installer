<?php

namespace App\Installer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The one-time token in the link the installer prints when it starts. Only
 * its hash is stored, and it's used up when the browser first opens the
 * link, so the link is worthless once someone has opened it.
 */
class InstallerToken
{
    public function issue(): string
    {
        $token = Str::random(40);

        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), hash('sha256', $token));
        File::chmod($this->path(), 0600);

        return $token;
    }

    /**
     * Uses up the token when it matches, so it works only once.
     */
    public function consume(?string $token): bool
    {
        if ($token === null || $token === '' || ! File::exists($this->path())) {
            return false;
        }

        if (! hash_equals(File::get($this->path()), hash('sha256', $token))) {
            return false;
        }

        File::delete($this->path());

        return true;
    }

    protected function path(): string
    {
        return storage_path('app/private/installer-token');
    }
}
