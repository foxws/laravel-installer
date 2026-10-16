<?php

declare(strict_types=1);

namespace App\Installer;

readonly class InstalledUnits
{
    /**
     * @param  list<string>  $services  The units the installer starts or enables, to follow their state.
     * @param  list<string>  $start  The services to start; systemd starts what they depend on and want.
     * @param  list<string>  $enable  The sockets and timers to enable and start.
     */
    public function __construct(
        public array $services,
        public array $start,
        public array $enable,
    ) {}
}
