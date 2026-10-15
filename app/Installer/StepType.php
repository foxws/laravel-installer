<?php

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
}
