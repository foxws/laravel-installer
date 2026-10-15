<?php

namespace App\Installer;

/**
 * A value a step asks for before it runs its command, such as the admin's
 * email address. Secret inputs, like passwords, never end up in the
 * command's arguments or in the output the installer shows.
 */
readonly class Input
{
    public function __construct(
        public string $name,
        public string $label,
        public InputType $type = InputType::Text,
        public string $rules = 'required',
        public bool $secret = false,
    ) {}
}
