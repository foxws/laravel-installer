<?php

declare(strict_types=1);

namespace App\Installer;

readonly class InstalledUnits
{
    /**
     * @param  list<string>  $services  The units the installer starts or enables, to follow their state.
     * @param  list<string>  $start  The services to start; systemd starts what they depend on and want.
     * @param  list<string>  $enable  The sockets and timers to enable and start.
     * @param  list<string>  $wake  The addresses of the on-demand sockets, such as "127.0.0.1:8000", to connect to so they start the app.
     */
    public function __construct(
        public array $services,
        public array $start,
        public array $enable,
        public array $wake = [],
    ) {}
}
