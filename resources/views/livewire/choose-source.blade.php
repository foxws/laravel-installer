<div class="flex min-h-screen flex-col">
    <header class="navbar border-b border-base-300 bg-base-100 px-4">
        <div class="navbar-start">
            <span class="font-semibold">Laravel installer</span>
        </div>
    </header>

    <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 lg:grid lg:grid-cols-[12rem_minmax(0,1fr)] lg:gap-10">
        <aside class="mb-6 lg:sticky lg:top-8 lg:mb-0 lg:self-start">
            <x-steps :steps="['Repository', 'Version', 'Check']" :current="$current" />
        </aside>

        <x-card>
            @if($current === 0)
                <h1 class="card-title text-2xl">Choose the app</h1>

                <div class="step-content">
                    <p>
                        This installer is a wizard for Laravel apps. It fetches the app from its git repository into the install location,
                        checks that the app's installer can work here, and then walks you through its steps.
                    </p>
                </div>

                <form wire:submit="fetch" class="mt-4 space-y-2">
                    <x-input id="url" label="Repository" type="url" wire:model="url" error="url"
                        placeholder="https://github.com/example/app.git" autocomplete="off" />

                    @unless($this->hasToken)
                        <x-input id="token" label="Access token (for a private repository)" type="password" wire:model="token"
                            autocomplete="new-password" />
                    @endunless

                    <x-button type="submit" variant="primary" target="fetch" loading="Fetching…" class="mt-2">
                        Continue
                    </x-button>
                </form>
            @endif

            @if($current === 1)
                <h1 class="card-title text-2xl">Choose the version</h1>

                <p class="text-base-content/60">
                    From <code class="break-all">{{ $repository }}</code>. Releases are tags; a branch installs its latest commit.
                </p>

                <form wire:submit="check" class="mt-4 space-y-2">
                    <div role="tablist" class="tabs tabs-box w-fit">
                        @foreach(\App\Installer\RefType::cases() as $type)
                            <input type="radio" name="ref-type" class="tab" value="{{ $type->value }}" wire:key="ref-type-{{ $type->value }}"
                                wire:model.live="refType" aria-label="{{ $type->label() }}">
                        @endforeach
                    </div>

                    @if($refType === \App\Installer\RefType::Commit->value)
                        <x-input id="ref" label="Commit hash" wire:model.live.debounce.300ms="ref" error="ref"
                            placeholder="The full hash, with 40 characters" autocomplete="off" class="font-mono" />
                    @else
                        @php($refs = $refType === \App\Installer\RefType::Tag->value ? $tags : $branches)

                        <fieldset class="fieldset">
                            <label for="ref" class="fieldset-legend">{{ \App\Installer\RefType::from($refType)->label() }}</label>

                            @if($refs === [])
                                <p class="text-base-content/60">This repository has none.</p>
                            @else
                                <select id="ref" wire:model.live="ref" @class(['select w-full', 'select-error' => $errors->has('ref')])>
                                    @foreach($refs as $name => $sha)
                                        <option wire:key="ref-{{ $name }}" value="{{ $name }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            @endif

                            @error('ref')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    @endif

                    @if($this->sha)
                        <p class="text-sm text-base-content/60">
                            Installs commit <code>{{ $this->sha }}</code>.
                        </p>
                    @endif

                    <nav class="card-actions mt-6 justify-between">
                        <x-button wire:click="previous" variant="ghost">Back</x-button>

                        <x-button type="submit" variant="primary" target="check" loading="Fetching…" :disabled="! $this->sha">
                            Fetch and check
                        </x-button>
                    </nav>
                </form>
            @endif

            @if($current === 2)
                <h1 class="card-title text-2xl">Check the installer</h1>

                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt class="text-base-content/60">Repository</dt>
                    <dd class="break-all"><code>{{ $checkedOut['url'] }}</code></dd>
                    <dt class="text-base-content/60">{{ \App\Installer\RefType::from($checkedOut['ref_type'])->label() }}</dt>
                    <dd class="break-all">{{ $checkedOut['ref'] }}</dd>
                    <dt class="text-base-content/60">Commit</dt>
                    <dd class="break-all"><code>{{ $checkedOut['sha'] }}</code></dd>
                </dl>

                <x-checks :checks="$this->preflight->checks" class="mt-4" />

                @if($this->preflight->hasFailures())
                    <p class="mt-4 font-medium text-error">
                        Fix the problems above, or go back and choose another version.
                    </p>
                @endif

                <nav class="card-actions mt-6 justify-between">
                    <x-button wire:click="previous" variant="ghost">Back</x-button>

                    <x-button wire:click="start" variant="primary" target="start" :disabled="$this->preflight->hasFailures()">
                        Start the installer
                    </x-button>
                </nav>
            @endif
        </x-card>
    </main>

    <x-footer />
</div>
