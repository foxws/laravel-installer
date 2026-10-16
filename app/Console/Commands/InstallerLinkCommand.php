<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Installer\InstallerToken;
use Illuminate\Console\Command;
use Illuminate\Support\Uri;

class InstallerLinkCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'installer:link';

    /**
     * @var string
     */
    protected $description = 'Print a new one-time link to open the installer with';

    public function handle(InstallerToken $token): int
    {
        $url = Uri::route('installer')
            ->withPath('/')
            ->withQuery(['token' => $token->issue()]);

        $this->components->info('Open the installer in your browser. The link works once.');
        $this->line("  {$url}");

        return self::SUCCESS;
    }
}
