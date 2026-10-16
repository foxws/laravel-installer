<?php

declare(strict_types=1);

namespace App\Installer;

enum CheckStatus: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Fail = 'fail';
    case Unknown = 'unknown';
}
