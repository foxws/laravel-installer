<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Installer\Git;
use App\Installer\GitRefs;
use App\Installer\InstallerMode;
use App\Installer\InstallerPreflight;
use App\Installer\RefType;
use App\Installer\RequirementsReport;
use App\Installer\Source;
use App\Installer\SourceException;
use App\Installer\Workspace;
use App\Installer\WorkspaceCheckout;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Asks for the app's git repository and the branch, tag or commit to
 * install, checks it out into the workspace, and checks whether its
 * installer can work here before the installer starts its steps.
 */
class ChooseSource extends Component
{
    public string $url = '';

    /**
     * A token for a private repository. It moves to the session as soon as
     * it's used, so it doesn't travel back and forth with the page.
     */
    public string $token = '';

    public string $refType = RefType::Branch->value;

    public string $ref = '';

    /**
     * Locked, so the browser can't skip the check.
     */
    #[Locked]
    public int $current = 0;

    /**
     * The repository the branches and tags were read from.
     */
    #[Locked]
    public ?string $repository = null;

    /**
     * @var array<string, string>
     */
    #[Locked]
    public array $branches = [];

    /**
     * @var array<string, string>
     */
    #[Locked]
    public array $tags = [];

    /**
     * What was checked out into the workspace.
     *
     * @var array{url: string, ref_type: string, ref: string, sha: string}|null
     */
    #[Locked]
    public ?array $checkedOut = null;

    public function mount(): void
    {
        if (InstallerMode::isBaked()) {
            $this->redirectRoute('installer');

            return;
        }

        $this->url = Source::current()->url ?? (string) Config::get('installer.source');
    }

    /**
     * Whether the installer has a token of its own, so it doesn't ask for one.
     */
    #[Computed]
    public function hasToken(): bool
    {
        return filled(Config::get('installer.git_token'));
    }

    /**
     * The commit the chosen branch, tag or commit points to.
     */
    #[Computed]
    public function sha(): ?string
    {
        $type = RefType::tryFrom($this->refType);

        return $type === null ? null : (new GitRefs($this->branches, $this->tags))->sha($type, $this->ref);
    }

    #[Computed]
    public function preflight(): ?RequirementsReport
    {
        return $this->checkedOut === null ? null : app(InstallerPreflight::class)->check(InstallerMode::sourcePath());
    }

    public function fetch(Git $git): void
    {
        $this->url = trim($this->url);

        $this->validate([
            'url' => ['required', 'string', function (string $attribute, string $value, Closure $fail): void {
                if (! Git::isValidUrl($value)) {
                    $fail(SourceException::invalidUrl()->getMessage());
                }
            }],
        ], attributes: ['url' => 'repository']);

        if ($this->token !== '') {
            Source::rememberToken($this->token);
            $this->token = '';
        }

        try {
            $refs = $git->refs($this->url, Source::token());
        } catch (SourceException $exception) {
            $this->addError('url', $exception->getMessage());

            return;
        }

        if ($refs->branches === [] && $refs->tags === []) {
            $this->addError('url', 'This repository has no branches or tags yet.');

            return;
        }

        $this->repository = $this->url;
        $this->branches = $refs->branches;
        $this->tags = $refs->tags;
        $this->checkedOut = null;

        // A release is the safest choice, so the newest tag comes first...
        $this->refType = $refs->tags !== [] ? RefType::Tag->value : RefType::Branch->value;
        $this->ref = $this->defaultRef();
        $this->current = 1;
    }

    public function updatedRefType(): void
    {
        $this->ref = $this->defaultRef();
        $this->resetValidation('ref');
    }

    public function check(Git $git, WorkspaceCheckout $checkout): void
    {
        if ($this->repository === null) {
            return;
        }

        $type = RefType::tryFrom($this->refType);
        $this->ref = trim($this->ref);

        if ($type === null || $this->sha === null) {
            $this->addError('ref', $type === RefType::Commit
                ? 'Enter the full commit hash, with 40 characters.'
                : "Choose a {$type?->label()} of the repository.");

            return;
        }

        $workspace = Workspace::fromConfig();

        try {
            $checkout->ensureCanReceive($workspace, $this->repository);

            $sha = $git->checkout($this->repository, $this->ref, (string) $workspace->path, Source::token());
        } catch (SourceException $exception) {
            $this->addError('ref', $exception->getMessage());

            return;
        }

        // The workspace holds another version now...
        Source::forget();

        $this->checkedOut = ['url' => $this->repository, 'ref_type' => $type->value, 'ref' => $this->ref, 'sha' => $sha];
        $this->current = 2;
        unset($this->preflight);
    }

    public function start(): void
    {
        if ($this->checkedOut === null || $this->preflight?->hasFailures() !== false) {
            return;
        }

        (new Source(
            url: $this->checkedOut['url'],
            refType: RefType::from($this->checkedOut['ref_type']),
            ref: $this->checkedOut['ref'],
            sha: $this->checkedOut['sha'],
        ))->remember();

        $this->redirectRoute('installer');
    }

    public function previous(): void
    {
        $this->current = max(0, $this->current - 1);
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.choose-source')
            ->title('Choose the app · Laravel installer');
    }

    protected function defaultRef(): string
    {
        return match (RefType::tryFrom($this->refType)) {
            RefType::Tag => (string) array_key_first($this->tags),
            RefType::Branch => (string) array_key_first($this->branches),
            default => '',
        };
    }
}
