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
    | Podman Binary
    |--------------------------------------------------------------------------
    |
    | The Podman binary the installer runs, such as for the host checks and
    | the steps' commands in the app's containers. The image uses
    | "podman-remote", which talks to the host's Podman socket mounted at
    | "/run/podman/podman.sock".
    |
    */

    'podman' => env('INSTALLER_PODMAN', 'podman'),

    /*
    |--------------------------------------------------------------------------
    | Command Timeout
    |--------------------------------------------------------------------------
    |
    | How long one command of a command step may take, in seconds, such as
    | the migrations or creating the admin account.
    |
    */

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
    | The app's folder on the host, mounted into the installer. Secrets and
    | services steps render the app's presets into it with
    | foxws/laravel-podman. Without it, those steps can't run.
    |
    */

    'workspace' => env('INSTALLER_WORKSPACE', '/workspace'),

    /*
    |--------------------------------------------------------------------------
    | Environment File
    |--------------------------------------------------------------------------
    |
    | The app's ".env" file, relative to the workspace unless it's an
    | absolute path. Only "APP_NAME", "APP_URL", "APP_ENV" and the "PODMAN_*"
    | values are read from it, plus the keys a secrets step maps to its
    | secrets. The installer's own "PODMAN_*" environment takes precedence,
    | since it describes the host.
    |
    */

    'env_file' => env('INSTALLER_ENV_FILE', '.env'),

    /*
    |--------------------------------------------------------------------------
    | Allow Existing Installs
    |--------------------------------------------------------------------------
    |
    | The installer is meant for a first install, so the requirements stop
    | when the app is installed on the host already. Set this to continue
    | anyway, such as after an earlier run that stopped halfway.
    |
    */

    'allow_existing' => (bool) env('INSTALLER_ALLOW_EXISTING', false),

    /*
    |--------------------------------------------------------------------------
    | Quadlet Path
    |--------------------------------------------------------------------------
    |
    | Where the app's Quadlet units are installed: the host user's
    | "~/.config/containers/systemd", mounted into the installer.
    |
    */

    'quadlet_path' => env('INSTALLER_QUADLET_PATH', '/run/installer/quadlets'),

    /*
    |--------------------------------------------------------------------------
    | Unit Path
    |--------------------------------------------------------------------------
    |
    | Where the app's plain systemd units are installed, such as its
    | on-demand socket and timers: the host user's "~/.config/systemd/user",
    | mounted into the installer.
    |
    */

    'unit_path' => env('INSTALLER_UNIT_PATH', '/run/installer/units'),

    /*
    |--------------------------------------------------------------------------
    | D-Bus
    |--------------------------------------------------------------------------
    |
    | The "dbus-send" binary that reloads systemd and starts the services,
    | through the host user's D-Bus session bus, mounted into the installer
    | and set with "DBUS_SESSION_BUS_ADDRESS".
    |
    */

    'dbus_send' => env('INSTALLER_DBUS_SEND', 'dbus-send'),

];
