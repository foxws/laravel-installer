<?php

declare(strict_types=1);

use App\Installer\CheckStatus;
use App\Installer\DoctorReport;

function doctorFixture(string $name): string
{
    return dirname(__DIR__)."/fixtures/doctor/{$name}.json";
}

it('reads the checks lpod doctor wrote', function () {
    $report = DoctorReport::fromFile(doctorFixture('warnings'));

    expect($report->lpodVersion)->toBe('2.3.0')
        ->and($report->checks)->toHaveCount(2)
        ->and($report->checks[1]->status)->toBe(CheckStatus::Warn)
        ->and($report->checks[1]->fix)->toBe('loginctl enable-linger me')
        ->and($report->hasFailures())->toBeFalse()
        ->and($report->count(CheckStatus::Warn))->toBe(1);
});

it('has failures when a check fails', function () {
    expect(DoctorReport::fromFile(doctorFixture('failures'))->hasFailures())->toBeTrue();
});

it('is missing when the file is missing or not a doctor report', function (string $name) {
    expect(DoctorReport::fromFile(doctorFixture($name)))->toBeNull();
})->with(['missing', 'broken', 'unknown-status']);
