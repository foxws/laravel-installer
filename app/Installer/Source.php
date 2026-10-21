<?php

declare(strict_types=1);

namespace App\Installer;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * The git repository and the version of it that the installer checked out
 * into the workspace. It's kept in the session, so the browser can't change
 * it, together with a token for a private repository typed into the
 * installer.
 */
readonly class Source
{
    protected const string SessionKey = 'installer.source';

    protected const string TokenSessionKey = 'installer.git_token';

    public function __construct(
        public string $url,
        public RefType $refType,
        public string $ref,
        public string $sha,
    ) {}

    public static function current(): ?self
    {
        $source = session(static::SessionKey);

        if (! is_array($source)) {
            return null;
        }

        return new self($source['url'], RefType::from($source['ref_type']), $source['ref'], $source['sha']);
    }

    public static function forget(): void
    {
        session()->forget(static::SessionKey);
    }

    /**
     * The token for a private repository: the installer's own, or the one
     * typed into it.
     */
    public static function token(): ?string
    {
        $token = Config::get('installer.git_token') ?: session(static::TokenSessionKey);

        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function rememberToken(string $token): void
    {
        session()->put(static::TokenSessionKey, $token);
    }

    public function remember(): void
    {
        session()->put(static::SessionKey, [
            'url' => $this->url,
            'ref_type' => $this->refType->value,
            'ref' => $this->ref,
            'sha' => $this->sha,
        ]);
    }

    public function shortSha(): string
    {
        return Str::substr($this->sha, 0, 7);
    }

    /**
     * The repository's host and path, such as "github.com/example/app".
     */
    public function displayUrl(): string
    {
        return Str::of($this->url)->after('://')->rtrim('/')->chopEnd('.git')->toString();
    }

    /**
     * The version a tag stands for, when the manifest doesn't set one.
     */
    public function version(): ?string
    {
        return $this->refType === RefType::Tag ? $this->ref : null;
    }
}
