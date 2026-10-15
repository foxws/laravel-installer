<?php

namespace App\Installer;

enum CheckStatus: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Fail = 'fail';

    /**
     * Something the installer can't see from inside its container, such as
     * linger. Without "lpod doctor --json" it lists these with their fix.
     */
    case Unknown = 'unknown';
}
