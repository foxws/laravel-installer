<?php

declare(strict_types=1);

namespace App\Installer;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * Whether a checked out app's installer can work with this installer: its
 * manifest is valid, it supports this version of the installer, and what
 * its steps need is mounted.
 */
class InstallerPreflight
{
    public function __construct(
        protected Podman $podman,
    ) {}

    public function check(string $path): RequirementsReport
    {
        try {
            $manifest = Manifest::fromPath($path);
        } catch (InvalidManifestException $exception) {
            return new RequirementsReport([
                new RequirementCheck('manifest', CheckStatus::Fail, $exception->getMessage()),
            ]);
        }

        $types = array_map(fn (Step $step): StepType => $step->type, $manifest->steps);
        $needs = fn (StepType ...$needed): bool => collect($needed)->contains(fn (StepType $type): bool => in_array($type, $types, true));

        $count = count($manifest->steps);

        return new RequirementsReport(array_values(array_filter([
            new RequirementCheck('manifest', CheckStatus::Ok, "The installer of {$manifest->name} is valid, with {$count} ".Str::plural('step', $count)),
            $this->version($manifest),
            $needs(StepType::Requirements, StepType::Command, StepType::Secrets, StepType::Services) ? $this->podman() : null,
            $needs(StepType::Services) ? $this->folder('quadlets', Config::string('installer.quadlet_path'), 'the Quadlet folder', '-v "$HOME/.config/containers/systemd:/run/installer/quadlets"') : null,
            $needs(StepType::Services) ? $this->folder('units', Config::string('installer.unit_path'), 'the systemd user folder', '-v "$HOME/.config/systemd/user:/run/installer/units"') : null,
            $needs(StepType::Services) ? $this->bus() : null,
            $needs(StepType::Secrets, StepType::Services) ? $this->environmentFile() : null,
        ])));
    }

    protected function version(Manifest $manifest): ?RequirementCheck
    {
        if ($manifest->installer === null) {
            return null;
        }

        $version = Config::string('installer.version');

        try {
            (new VersionParser)->normalize($version);
        } catch (UnexpectedValueException) {
            return new RequirementCheck('version', CheckStatus::Warn, "{$manifest->name} needs installer {$manifest->installer}, but this is a development build ({$version}), so it can't tell whether it matches");
        }

        if (Semver::satisfies($version, $manifest->installer)) {
            return new RequirementCheck('version', CheckStatus::Ok, "{$manifest->name} supports installer {$version}");
        }

        return new RequirementCheck(
            'version',
            CheckStatus::Fail,
            "{$manifest->name} needs installer {$manifest->installer}, but this is installer {$version}",
            'Start an installer image that matches, or choose another version of the app.',
        );
    }

    protected function podman(): RequirementCheck
    {
        if ($this->podman->info() !== null) {
            return new RequirementCheck('podman', CheckStatus::Ok, 'The Podman socket is mounted');
        }

        return new RequirementCheck(
            'podman',
            CheckStatus::Fail,
            "The installer can't reach Podman through its socket, which its steps need",
            <<<'FIX'
            Start the Podman socket on the host, and mount it:
              systemctl --user enable --now podman.socket
              -v "$XDG_RUNTIME_DIR/podman/podman.sock:/run/podman/podman.sock"
            FIX,
        );
    }

    protected function folder(string $id, string $path, string $label, string $mount): RequirementCheck
    {
        if (is_dir($path) && is_writable($path)) {
            return new RequirementCheck($id, CheckStatus::Ok, Str::ucfirst("{$label} is mounted"));
        }

        return new RequirementCheck($id, CheckStatus::Fail, "The services step needs {$label}, but it isn't mounted at {$path}, or the installer can't write to it", "Start the installer with:\n  {$mount}");
    }

    protected function bus(): RequirementCheck
    {
        $address = Config::get('installer.dbus_address');
        $socket = is_string($address) ? Str::after($address, 'unix:path=') : null;

        if ($socket !== null && $socket !== $address && file_exists($socket)) {
            return new RequirementCheck('bus', CheckStatus::Ok, "The user's D-Bus session bus is mounted");
        }

        return new RequirementCheck(
            'bus',
            CheckStatus::Fail,
            "The services step needs the user's D-Bus session bus to reach systemd, but it isn't mounted",
            <<<'FIX'
            Start the installer with:
              -v "$XDG_RUNTIME_DIR/bus:/run/installer/bus"
              -e DBUS_SESSION_BUS_ADDRESS=unix:path=/run/installer/bus
            FIX,
        );
    }

    protected function environmentFile(): RequirementCheck
    {
        $envFile = Workspace::fromConfig()->envFile;

        if ($envFile !== null && is_file($envFile)) {
            return new RequirementCheck('env', CheckStatus::Ok, 'The app has a .env file');
        }

        return new RequirementCheck(
            'env',
            CheckStatus::Warn,
            'The app has no .env file yet, which the secrets and services steps read',
            'Create it in the workspace before those steps, such as from the app\'s .env.example.',
        );
    }
}
