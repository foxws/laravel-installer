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
    | Image
    |--------------------------------------------------------------------------
    |
    | The installer's own image, which runs short-lived helpers on the host,
    | such as waking an on-demand app. When it's not set, the installer
    | looks it up from its own container.
    |
    */

    'image' => env('INSTALLER_IMAGE'),

    /*
    |--------------------------------------------------------------------------
    | Workspace
    |--------------------------------------------------------------------------
    |
    | The app's folder on the host, mounted into the installer at "/workspace",
    | and its ".env" file, relative to the workspace unless it's an absolute
    | path. Services steps render the app's presets into it with
    | foxws/laravel-podman. Only "APP_NAME", "APP_URL", "APP_ENV" and the
    | "PODMAN_*" values are read from the ".env" file. The installer's own
    | "PODMAN_*" environment, set by "lpod installer", takes precedence.
    |
    */

    'workspace' => env('INSTALLER_WORKSPACE', '/workspace'),

    'env_file' => env('INSTALLER_ENV_FILE', '.env'),

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
