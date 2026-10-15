<?php

use App\Livewire\Installer;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function useDoctorReport(string $name): void
{
    config(['installer.doctor_report' => dirname(__DIR__)."/fixtures/doctor/{$name}.json"]);
}

it('shows the first step', function () {
    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertSee('Example installer')
        ->assertSee('This installer sets up the app');
});

it('moves through the steps', function () {
    useDoctorReport('warnings');

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSet('current', 1)
        ->assertSee('Linger is off')
        ->assertSee('loginctl enable-linger me')
        ->call('next')
        ->assertSet('current', 2)
        ->assertSee('The app is installed')
        ->call('next')
        ->assertSet('current', 2)
        ->call('previous')
        ->assertSet('current', 1);
});

it('stops at the requirements while the host has problems', function () {
    useDoctorReport('failures');

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSee('me has no entry in /etc/subuid')
        ->assertSee('Fix the problems above on the host')
        ->call('next')
        ->assertSet('current', 1);
});

it('stops at the requirements without a host report', function () {
    useDoctorReport('missing');

    Livewire::test(Installer::class)
        ->call('next')
        ->assertSee('The host report is missing')
        ->call('next')
        ->assertSet('current', 1);
});

it('does not let the browser skip steps', function () {
    useDoctorReport('failures');

    Livewire::test(Installer::class)->set('current', 2);
})->throws(CannotUpdateLockedPropertyException::class);
