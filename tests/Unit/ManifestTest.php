<?php

use App\Installer\InvalidManifestException;
use App\Installer\Manifest;
use App\Installer\StepType;
use Illuminate\Support\Facades\File;

/**
 * @param  array<string, string>  $files
 */
function installerFolder(array $files): string
{
    $path = sys_get_temp_dir().'/installer-'.bin2hex(random_bytes(4));

    File::ensureDirectoryExists($path);

    foreach ($files as $name => $contents) {
        File::put("{$path}/{$name}", $contents);
    }

    return $path;
}

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
    'duplicate ids' => [['manifest.yml' => "name: App\nsteps:\n  - { id: first, file: a.md }\n  - { id: first, file: a.md }", 'a.md' => ''], 'first are used more than once'],
]);
