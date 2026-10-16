<?php

declare(strict_types=1);

use App\Installer\InvalidManifestException;
use App\Installer\Manifest;
use App\Installer\StepType;

it('reads the steps of the example installer', function () {
    $manifest = Manifest::fromPath(dirname(__DIR__, 2).'/installer');

    expect($manifest->name)->toBe('Example')
        ->and(array_map(fn ($step) => $step->id, $manifest->steps))->toBe(['welcome', 'requirements', 'finish'])
        ->and($manifest->steps[1]->type)->toBe(StepType::Requirements)
        ->and($manifest->steps[0]->title)->toBe('Welcome');
});

it('takes the title from the front matter, or else from the id', function () {
    $manifest = Manifest::fromPath(installerFolder([
        'manifest.yml' => "name: App\nsteps:\n  - { id: first, file: a.md }\n  - { id: storage-paths, file: b.md }",
        'a.md' => "---\ntitle: Hello\n---\n\nBody",
        'b.md' => 'Body',
    ]));

    expect($manifest->steps[0]->title)->toBe('Hello')
        ->and($manifest->steps[1]->title)->toBe('Storage Paths')
        ->and($manifest->steps[0]->html)->not->toContain('title:');
});

it('strips raw html from the steps', function () {
    $manifest = Manifest::fromPath(installerFolder([
        'manifest.yml' => "name: App\nsteps:\n  - { id: first, file: a.md }",
        'a.md' => "Hi <script>alert(1)</script>\n\n[link](javascript:alert(1))",
    ]));

    expect($manifest->steps[0]->html)->not->toContain('<script>')
        ->and($manifest->steps[0]->html)->not->toContain('javascript:');
});

it('rejects invalid manifests', function (array $files, string $reason) {
    expect(fn () => Manifest::fromPath(installerFolder($files)))
        ->toThrow(InvalidManifestException::class, $reason);
})->with([
    'missing' => [[], 'No installer manifest found'],
    'broken yaml' => [['manifest.yml' => "name: [\n"], 'is invalid'],
    'no steps' => [['manifest.yml' => 'name: App'], 'needs a "name" and a list of "steps"'],
    'step without file' => [['manifest.yml' => "name: App\nsteps:\n  - { id: first }"], 'step 0 needs an "id" and a "file"'],
    'unknown type' => [['manifest.yml' => "name: App\nsteps:\n  - { id: first, type: magic, file: a.md }", 'a.md' => ''], 'unknown type'],
    'missing markdown' => [['manifest.yml' => "name: App\nsteps:\n  - { id: first, file: a.md }"], "which doesn't exist"],
    'command without run' => [['manifest.yml' => "name: App\nsteps:\n  - { id: first, type: command, file: a.md }", 'a.md' => ''], 'needs a "run"'],
    'command as a string' => [['manifest.yml' => "name: App\nsteps:\n  - { id: first, type: command, file: a.md, run: { container: app, command: 'php artisan x' } }", 'a.md' => ''], 'as a list of arguments'],
    'secret in arguments' => [['manifest.yml' => "name: App\nsteps:\n  - { id: first, type: command, file: a.md, inputs: [{ name: password, type: password }], run: { container: app, command: [x, '--password={password}'] } }", 'a.md' => ''], 'can only be passed through "stdin" or "env"'],
    'lower case env' => [['manifest.yml' => "name: App\nsteps:\n  - { id: first, type: command, file: a.md, run: { container: app, command: [x], env: { token: y } } }", 'a.md' => ''], 'must map upper case names'],
    'duplicate ids' => [['manifest.yml' => "name: App\nsteps:\n  - { id: first, file: a.md }\n  - { id: first, file: a.md }", 'a.md' => ''], 'first are used more than once'],
]);

it('reads the inputs and command of a command step', function () {
    $step = Manifest::fromPath(dirname(__DIR__).'/fixtures/installer-command')->steps[0];

    expect($step->type)->toBe(StepType::Command)
        ->and($step->title)->toBe('Admin account')
        ->and(array_map(fn ($input) => $input->name, $step->inputs))->toBe(['name', 'email', 'password'])
        ->and(array_map(fn ($input) => $input->name, $step->secretInputs()))->toBe(['password'])
        ->and($step->run->container)->toBe('systemd-demo')
        ->and($step->run->stdin)->toBe('{password}')
        ->and($step->run->skipIf)->toBe(['php', 'artisan', 'users:exists', '--super-admin']);
});

it('reads the presets of a services step', function () {
    $manifest = Manifest::fromPath(installerFolder([
        'manifest.yml' => "name: Demo\nsteps:\n  - { id: services, type: services, file: services.md, presets: [production, proxy] }\n",
        'services.md' => '# Services',
    ]));

    expect($manifest->steps[0]->type)->toBe(StepType::Services)
        ->and($manifest->steps[0]->presets)->toBe(['production', 'proxy']);
});

it('refuses presets that are not a list of names', function () {
    Manifest::fromPath(installerFolder([
        'manifest.yml' => "name: Demo\nsteps:\n  - { id: services, type: services, file: services.md, presets: production }\n",
        'services.md' => '# Services',
    ]));
})->throws(InvalidManifestException::class, 'must be a list of preset names');

it('reads where a secrets step takes its values from', function () {
    $manifest = Manifest::fromPath(installerFolder([
        'manifest.yml' => "name: Demo\nsteps:\n  - id: secrets\n    type: secrets\n    file: secrets.md\n    presets: [production]\n    values: { POSTGRES_PASSWORD: DB_PASSWORD }\n    files: { /app/.env: .env.production }\n",
        'secrets.md' => '# Secrets',
    ]));

    expect($manifest->steps[0]->type)->toBe(StepType::Secrets)
        ->and($manifest->steps[0]->presets)->toBe(['production'])
        ->and($manifest->steps[0]->secretValues)->toBe(['POSTGRES_PASSWORD' => 'DB_PASSWORD'])
        ->and($manifest->steps[0]->secretFiles)->toBe(['/app/.env' => '.env.production']);
});

it('refuses secret sources that are not a map of names', function () {
    Manifest::fromPath(installerFolder([
        'manifest.yml' => "name: Demo\nsteps:\n  - { id: secrets, type: secrets, file: secrets.md, values: [DB_PASSWORD] }\n",
        'secrets.md' => '# Secrets',
    ]));
})->throws(InvalidManifestException::class, 'must map secret targets to names');

it('refuses an input named application', function () {
    Manifest::fromPath(installerFolder([
        'manifest.yml' => "name: Demo\nsteps:\n  - { id: admin, type: command, file: admin.md, inputs: [{ name: application }], run: { container: app, command: [php] } }\n",
        'admin.md' => '# Admin',
    ]));
})->throws(InvalidManifestException::class, 'can\'t be named "application"');
