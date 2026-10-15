<?php

namespace App\Installer;

readonly class DoctorCheck
{
    public function __construct(
        public string $id,
        public CheckStatus $status,
        public string $message,
        public string $fix = '',
    ) {}
}
