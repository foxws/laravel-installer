<?php

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

    /*
    |--------------------------------------------------------------------------
    | Workspace
    |--------------------------------------------------------------------------
    |
    | The app's folder on the host, mounted into the installer, and its ".env"
    | file. Services steps render the app's presets into it with
    | foxws/laravel-podman. Only "APP_NAME", "APP_URL", "APP_ENV" and the
    | "PODMAN_*" values are read from the ".env" file. The installer's own
    | "PODMAN_*" environment, set by "lpod installer", takes precedence.
    |
    */

    'workspace' => env('INSTALLER_WORKSPACE'),

    'env_file' => env('INSTALLER_ENV_FILE'),

    /*
    |--------------------------------------------------------------------------
    | Systemd
    |--------------------------------------------------------------------------
    |
    | Where the rendered units are installed: the host user's Quadlet folder
    | ("~/.config/containers/systemd") and systemd user folder
    | ("~/.config/systemd/user"), mounted into the installer. Systemd itself
    | is reached through the user's D-Bus session bus.
    |
    */

    'quadlet_path' => env('INSTALLER_QUADLET_PATH', '/run/installer/quadlets'),

    'unit_path' => env('INSTALLER_UNIT_PATH', '/run/installer/units'),

    'dbus_send' => env('INSTALLER_DBUS_SEND', 'dbus-send'),

];
