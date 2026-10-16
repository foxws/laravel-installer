<?php

declare(strict_types=1);

use App\Installer\CheckStatus;
use App\Installer\PodmanInfoReport;

function podmanInfo(array $overrides = []): array
{
    $info = json_decode(file_get_contents(dirname(__DIR__).'/fixtures/podman/rootless.json'), true);

    foreach ($overrides as $key => $value) {
        data_set($info, $key, $value);
    }

    return $info;
}

function statuses(array $checks): array
{
    return collect($checks)->mapWithKeys(fn ($check) => [$check->id => $check->status])->all();
}

it('checks a rootless host through the socket', function () {
    $report = PodmanInfoReport::fromInfo(podmanInfo());

    expect($report->fromHost)->toBeFalse()
        ->and($report->hasFailures())->toBeFalse()
        ->and(statuses($report->checks))->toBe([
            'podman' => CheckStatus::Ok,
            'cgroups' => CheckStatus::Ok,
            'subids' => CheckStatus::Ok,
            'linger' => CheckStatus::Unknown,
            'ports' => CheckStatus::Unknown,
        ]);
});

it('fails on a podman without quadlet', function () {
    $report = PodmanInfoReport::fromInfo(podmanInfo(['version.Version' => '5.5.1']));

    expect($report->hasFailures())->toBeTrue()
        ->and($report->checks[0]->message)->toContain('5.5.1');
});

it('fails without subordinate ids', function () {
    $report = PodmanInfoReport::fromInfo(podmanInfo(['host.idMappings.uidmap' => [['container_id' => 0, 'host_id' => 1000, 'size' => 1]]]));

    expect(statuses($report->checks)['subids'])->toBe(CheckStatus::Fail);
});

it('warns without cgroup v2', function () {
    $report = PodmanInfoReport::fromInfo(podmanInfo(['host.cgroupVersion' => 'v1']));

    expect(statuses($report->checks)['cgroups'])->toBe(CheckStatus::Warn)
        ->and($report->hasFailures())->toBeFalse();
});

it('skips the rootless checks when podman runs as root', function () {
    $report = PodmanInfoReport::fromInfo(podmanInfo(['host.security.rootless' => false]));

    expect(array_keys(statuses($report->checks)))->toBe(['podman', 'cgroups']);
});

it('fails when the socket cannot be reached', function () {
    $report = PodmanInfoReport::fromInfo(null);

    expect($report->hasFailures())->toBeTrue()
        ->and($report->checks[0]->fix)->toContain('podman.socket');
});
