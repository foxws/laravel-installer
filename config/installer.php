<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Installer Path
    |--------------------------------------------------------------------------
    |
    | The folder with the app's "manifest.yml" and its Markdown steps. An app's
    | installer image copies its own "installer/" folder over the example one.
    |
    */

    'path' => env('INSTALLER_PATH') ?: base_path('installer'),

    /*
    |--------------------------------------------------------------------------
    | Doctor Report
    |--------------------------------------------------------------------------
    |
    | The output of "lpod doctor --json", written on the host before the
    | installer starts and mounted into the container. The installer can't
    | check the host from inside its container, so it reads this instead.
    |
    */

    'doctor_report' => env('INSTALLER_DOCTOR_REPORT', '/run/installer/doctor.json'),

    /*
    |--------------------------------------------------------------------------
    | Podman
    |--------------------------------------------------------------------------
    |
    | The Podman binary that runs the steps' commands in the app's containers,
    | through the Podman socket mounted into the installer, and how long one
    | command may take, in seconds.
    |
    */

    'podman' => env('INSTALLER_PODMAN', 'podman'),

    'command_timeout' => (int) env('INSTALLER_COMMAND_TIMEOUT', 300),

];
