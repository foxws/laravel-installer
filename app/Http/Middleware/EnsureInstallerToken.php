<?php

namespace App\Http\Middleware;

use App\Installer\InstallerToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the browser in once it opened the link with the one-time token, and
 * keeps everyone else out. The token is taken out of the address right away,
 * so it doesn't end up in the browser history.
 */
class EnsureInstallerToken
{
    public const string SessionKey = 'installer.authorized';

    public function __construct(
        protected InstallerToken $token,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query->has('token')) {
            abort_unless($this->token->consume($request->query('token')), 403, 'This link has expired. Start the installer again for a new one.');

            $request->session()->regenerate();
            $request->session()->put(static::SessionKey, true);

            return redirect()->to($request->fullUrlWithoutQuery('token'));
        }

        abort_unless($request->session()->get(static::SessionKey) === true, 403, 'Open the link the installer printed when it started.');

        return $next($request);
    }
}
