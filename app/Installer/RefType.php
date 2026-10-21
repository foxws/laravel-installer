<?php

declare(strict_types=1);

namespace App\Installer;

enum RefType: string
{
    case Branch = 'branch';
    case Tag = 'tag';
    case Commit = 'commit';

    public function label(): string
    {
        return match ($this) {
            self::Branch => __('Branch'),
            self::Tag => __('Tag'),
            self::Commit => __('Commit'),
        };
    }
}
