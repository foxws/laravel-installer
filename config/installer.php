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

];
