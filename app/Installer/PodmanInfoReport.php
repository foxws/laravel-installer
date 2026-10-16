<?php

declare(strict_types=1);

namespace App\Installer;

/**
 * The host checks the installer can make from "podman info" through the
 * Podman socket. What it can't see from inside its container, like linger,
 * it lists as unknown, with how to check and fix it on the host.
 */
class PodmanInfoReport
{
    public const string MinimumVersion = '5.6';

    /**
     * @param  array<string, mixed>|null  $info  The output of "podman info", or null when the socket can't be reached
     */
    public static function fromInfo(?array $info): RequirementsReport
    {
        if ($info === null) {
            return new RequirementsReport([
                new RequirementCheck('podman', CheckStatus::Fail, "The installer can't reach Podman through its socket", 'systemctl --user enable --now podman.socket
Then start the installer again with the socket mounted at /run/podman/podman.sock.'),
            ]);
        }

        $version = (string) data_get($info, 'version.Version', '');
        $rootless = data_get($info, 'host.security.rootless') === true;

        $checks = [
            version_compare($version, static::MinimumVersion, '>=')
                ? new RequirementCheck('podman', CheckStatus::Ok, "Podman {$version} with Quadlet")
                : new RequirementCheck('podman', CheckStatus::Fail, "Podman {$version} has no \"podman quadlet\" command", 'Upgrade to Podman '.static::MinimumVersion.' or newer.'),

            data_get($info, 'host.cgroupVersion') === 'v2'
                ? new RequirementCheck('cgroups', CheckStatus::Ok, 'The host uses cgroup v2')
                : new RequirementCheck('cgroups', CheckStatus::Warn, "The host doesn't use cgroup v2, so services can't have resource limits", 'See your distribution\'s docs to boot with cgroup v2 (systemd.unified_cgroup_hierarchy=1).'),
        ];

        if ($rootless) {
            $checks[] = count(data_get($info, 'host.idMappings.uidmap', [])) > 1
                ? new RequirementCheck('subids', CheckStatus::Ok, 'Subordinate user and group IDs are set up')
                : new RequirementCheck('subids', CheckStatus::Fail, 'Your user has no subordinate user and group IDs', 'sudo usermod --add-subuids 100000-165535 --add-subgids 100000-165535 "$USER"
podman system migrate');

            $checks[] = new RequirementCheck('linger', CheckStatus::Unknown, "The installer can't see whether linger is on, so services start at boot", 'loginctl show-user "$USER" --property=Linger
loginctl enable-linger "$USER"');

            $checks[] = new RequirementCheck('ports', CheckStatus::Unknown, "The installer can't see whether rootless containers may publish ports 80 and 443, which the proxy needs", "cat /proc/sys/net/ipv4/ip_unprivileged_port_start
echo 'net.ipv4.ip_unprivileged_port_start=80' | sudo tee /etc/sysctl.d/99-lpod.conf
sudo sysctl --system");
        }

        return new RequirementsReport($checks);
    }
}
