<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureInstallerToken;
use App\Installer\InstallerToken;

beforeEach(function () {
    $this->withoutVite();
});

it('keeps out browsers without the link', function () {
    $this->get('/')->assertForbidden();
});

it('lets the browser in with the link, and takes the token out of the address', function () {
    $token = app(InstallerToken::class)->issue();

    $this->get("/?token={$token}")
        ->assertRedirect(url('/'))
        ->assertSessionHas(EnsureInstallerToken::SessionKey, true);

    $this->get('/')->assertOk()->assertSee('Example installer');
});

it('works only once', function () {
    $token = app(InstallerToken::class)->issue();

    $this->get("/?token={$token}")->assertRedirect();

    $this->flushSession();

    $this->get("/?token={$token}")->assertForbidden();
});

it('rejects a wrong token', function () {
    app(InstallerToken::class)->issue();

    $this->get('/?token=wrong')->assertForbidden();
});

it('prints a link that opens the installer', function () {
    $this->artisan('installer:link')
        ->expectsOutputToContain(url('/').'/?token=')
        ->assertSuccessful();
});

it('keeps livewire updates out without the link', function () {
    $this->withHeader('X-Livewire', 'true')
        ->postJson(route('default-livewire.update'), ['components' => []])
        ->assertForbidden();
});
