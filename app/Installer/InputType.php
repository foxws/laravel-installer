<?php

namespace App\Installer;

enum InputType: string
{
    case Text = 'text';
    case Email = 'email';
    case Password = 'password';
}
