<?php

declare(strict_types=1);

namespace App\Installer;

enum StepType: string
{
    /**
     * A page of Markdown, such as a welcome or a finish page.
     */
    case Content = 'content';

    /**
     * The host checks, through the Podman socket. The installer only
     * continues once none of them fail.
     */
    case Requirements = 'requirements';

    /**
     * Asks for its inputs, then runs a command in the app's container, such as
     * creating the admin account or running a seeder.
     */
    case Command = 'command';

    /**
     * Renders the app's presets into the workspace with foxws/laravel-podman,
     * installs them as Quadlets and starts them.
     */
    case Services = 'services';

    /**
     * Creates the Podman secrets the app's presets use, from the app's
     * ".env", files in the workspace, or values given in the installer.
     */
    case Secrets = 'secrets';
}
