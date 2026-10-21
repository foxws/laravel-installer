<?php

declare(strict_types=1);

use App\Installer\Git;
use App\Installer\SourceException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('lists the branches and tags, with the default branch and newest version first', function () {
    Process::fake(['*' => Process::result(output: implode("\n", [
        "ref: refs/heads/main\tHEAD",
        str_repeat('a', 40)."\tHEAD",
        str_repeat('b', 40)."\trefs/heads/develop",
        str_repeat('a', 40)."\trefs/heads/main",
        str_repeat('c', 40)."\trefs/tags/nightly",
        str_repeat('d', 40)."\trefs/tags/v1.2.0",
        str_repeat('e', 40)."\trefs/tags/v1.10.0",
        str_repeat('f', 40)."\trefs/tags/v1.10.0^{}",
    ]))]);

    $refs = app(Git::class)->refs('https://github.com/acme/app.git');

    expect($refs->defaultBranch)->toBe('main')
        ->and($refs->branches)->toBe(['main' => str_repeat('a', 40), 'develop' => str_repeat('b', 40)])
        ->and($refs->tags)->toBe(['v1.10.0' => str_repeat('f', 40), 'v1.2.0' => str_repeat('d', 40), 'nightly' => str_repeat('c', 40)]);

    Process::assertRan(fn (PendingProcess $process): bool => array_slice($process->command, -7) === ['ls-remote', '--symref', '--', 'https://github.com/acme/app.git', 'HEAD', 'refs/heads/*', 'refs/tags/*']
        && in_array('protocol.allow=never', $process->command, true)
        && $process->environment['GIT_TERMINAL_PROMPT'] === '0');
});

it('fetches only the chosen version into an empty workspace', function () {
    $workspace = installerFolder([]);
    Process::fake([
        '*rev-parse*' => Process::result(output: str_repeat('a', 40)."\n"),
        '*' => Process::result(),
    ]);

    $sha = app(Git::class)->checkout('https://github.com/acme/app.git', 'v1.2.0', $workspace);

    expect($sha)->toBe(str_repeat('a', 40));

    Process::assertRanTimes(fn (PendingProcess $process): bool => in_array('init', $process->command, true), 1);
    Process::assertRan(fn (PendingProcess $process): bool => array_slice($process->command, -8) === ['fetch', '--depth', '1', '--no-tags', '--no-recurse-submodules', 'origin', '--', 'v1.2.0']);
    Process::assertRan(fn (PendingProcess $process): bool => array_slice($process->command, -4) === ['checkout', '--quiet', '--detach', 'FETCH_HEAD']);
});

it('hands a token to git through its environment only', function () {
    Process::fake(['*' => Process::result(output: '')]);

    app(Git::class)->refs('https://github.com/acme/private.git', 'secret-token');

    Process::assertRan(fn (PendingProcess $process): bool => ! str_contains(implode(' ', $process->command), 'secret-token')
        && $process->environment['GIT_CONFIG_KEY_0'] === 'http.extraHeader'
        && $process->environment['GIT_CONFIG_VALUE_0'] === 'Authorization: Basic '.base64_encode('x-access-token:secret-token'));
});

it('hides the token in errors', function () {
    Process::fake(['*' => Process::result(errorOutput: 'fatal: bad credentials secret-token', exitCode: 128)]);

    expect(fn () => app(Git::class)->refs('https://github.com/acme/private.git', 'secret-token'))
        ->toThrow(fn (SourceException $exception) => expect($exception->getMessage())->toContain('bad credentials')->not->toContain('secret-token'));
});

it('only fetches https repositories', function (string $url) {
    Process::fake();

    expect(fn () => app(Git::class)->refs($url))->toThrow(SourceException::class, 'must be an https:// address');

    Process::assertNothingRan();
})->with([
    'ssh' => 'git@github.com:acme/app.git',
    'file' => 'file:///etc',
    'ext' => 'ext::sh -c id',
    'http' => 'http://github.com/acme/app.git',
    'credentials in the address' => 'https://user:pass@github.com/acme/app.git',
]);
