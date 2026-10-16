<?php

declare(strict_types=1);

namespace App\Installer;

use Illuminate\Support\Facades\Config;

/**
 * Whether the app is installed on this host already, so the installer isn't
 * run over a working install by mistake: it would reinstall and restart the
 * services, and could replace their secrets.
 */
class ExistingInstallCheck
{
    public static function forWorkspace(Workspace $workspace): ?RequirementCheck
    {
        if (! $workspace->isAvailable()) {
            return null;
        }

        try {
            $application = $workspace->application();
        } catch (ServicesException) {
            return null;
        }

        $unit = Config::string('installer.quadlet_path')."/{$application}.container";

        if (! is_file($unit)) {
            return new RequirementCheck('existing-install', CheckStatus::Ok, "{$application} isn't installed on this host yet");
        }

        if (Config::boolean('installer.allow_existing')) {
            return new RequirementCheck('existing-install', CheckStatus::Warn, "{$application} is installed on this host already. The installer will reinstall and restart its services");
        }

        return new RequirementCheck(
            'existing-install',
            CheckStatus::Fail,
            "{$application} is installed on this host already. The installer is only for a first install",
            <<<'FIX'
            To update it, follow the app's upgrade guide instead.
            If an earlier run of the installer stopped halfway, start it again with:
              -e INSTALLER_ALLOW_EXISTING=true
            FIX,
        );
    }
}
