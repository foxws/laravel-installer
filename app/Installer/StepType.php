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
     * The host checks from "lpod doctor --json". The installer only continues
     * once none of them fail.
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
}
