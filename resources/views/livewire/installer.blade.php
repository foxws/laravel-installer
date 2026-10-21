<div class="flex min-h-screen flex-col">
    <header class="navbar border-b border-base-300 bg-base-100 px-4">
        <div class="navbar-start gap-2">
            <span class="font-semibold">{{ $this->manifest->name }} installer</span>

            @if($this->manifest->version)
                <x-badge>{{ $this->manifest->version }}</x-badge>
            @endif
        </div>

        @if($this->manifest->links !== [])
            <nav class="navbar-end flex-wrap gap-1" aria-label="Links">
                @foreach($this->manifest->links as $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm">{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endif
    </header>

    <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 lg:grid lg:grid-cols-[12rem_minmax(0,1fr)] lg:gap-10">
        <aside class="mb-6 lg:sticky lg:top-8 lg:mb-0 lg:self-start">
            <x-steps :steps="array_column($this->manifest->steps, 'title')" :current="$current" />
        </aside>

        <div>
            @if($this->canSkip)
                <x-alert type="warning" class="mb-4">
                    Steps can be skipped, for testing the installer. Don't use this for a real install.
                </x-alert>
            @endif

            <x-card>
                <h1 class="card-title text-2xl">{{ $this->step->title }}</h1>

                <div class="step-content">
                    {!! $this->step->html !!}
                </div>

                @if($this->step->type === \App\Installer\StepType::Requirements)
                    <ul class="mt-4 space-y-3">
                        @foreach($this->report->checks as $check)
                            <li wire:key="check-{{ $check->id }}">
                                <x-alert :type="$check->status->alertType()">
                                    <p class="flex gap-2">
                                        <span class="font-bold" aria-hidden="true">{{ $check->status->symbol() }}</span>
                                        <span class="sr-only">{{ $check->status->label() }}:</span>
                                        <span>{{ $check->message }}</span>
                                    </p>

                                    @if($check->fix !== '')
                                        <x-slot:fix>{{ $check->fix }}</x-slot:fix>
                                    @endif
                                </x-alert>
                            </li>
                        @endforeach
                    </ul>

                    @if($this->report->hasFailures())
                        <p class="mt-4 font-medium text-error">
                            Fix the problems above on the host, then start the installer again.
                        </p>
                    @endif
                @endif

                @if($this->step->type === \App\Installer\StepType::Command)
                    @if(in_array($this->step->id, $completed, true))
                        <x-alert type="success" class="mt-4">✓ Done.</x-alert>
                    @else
                        <form wire:submit="runCommand" class="mt-4 space-y-2">
                            @foreach($this->step->inputs as $input)
                                <div wire:key="input-{{ $input->name }}">
                                    <x-input id="input-{{ $input->name }}" :label="$input->label" type="{{ $input->type->value }}"
                                        wire:model="values.{{ $input->name }}" :error="'values.'.$input->name"
                                        autocomplete="{{ $input->secret ? 'new-password' : 'off' }}" />
                                </div>
                            @endforeach

                            <x-button type="submit" variant="neutral" target="runCommand" loading="Running…" class="mt-2">
                                Run
                            </x-button>
                        </form>
                    @endif

                    @error('command')
                        <x-alert type="error" class="mt-4">{{ $message }}</x-alert>
                    @enderror

                    @if($output)
                        <x-code class="mt-4 max-h-80 overflow-auto">{{ $output }}</x-code>
                    @endif
                @endif

                @if($this->step->type === \App\Installer\StepType::Services)
                    @if($this->workspace === null)
                        <x-alert type="warning" class="mt-4">
                            No workspace is mounted, so the installer can't install the services.
                            Start it again with <code>lpod installer IMAGE --workspace PATH</code>.
                        </x-alert>
                    @else
                        <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                            <dt class="text-base-content/60">App</dt>
                            <dd>{{ $this->workspace['name'] }} ({{ $this->workspace['url'] }})</dd>
                            <dt class="text-base-content/60">Folder</dt>
                            <dd class="break-all"><code>{{ $this->workspace['path'] }}</code></dd>
                            <dt class="text-base-content/60">Presets</dt>
                            <dd>{{ implode(', ', $this->workspace['presets']) }}</dd>
                        </dl>

                        @if($this->serviceStates === [])
                            <x-button wire:click="installServices" variant="neutral" target="installServices" loading="Installing…" class="mt-4 self-start">
                                Install and start
                            </x-button>
                        @else
                            <ul class="list mt-4 rounded-box border border-base-300 text-sm" @unless($this->canContinue) wire:poll.3s="refreshServices" @endunless>
                                @foreach($this->serviceStates as $service => $state)
                                    <li wire:key="service-{{ $service }}" class="list-row items-center">
                                        <code class="list-col-grow break-all">{{ $service }}</code>
                                        <x-badge
                                            :type="match ($state) { 'active' => 'success', 'failed' => 'error', default => 'ghost' }"
                                            :spinner="in_array($state, ['activating', 'inactive'], true)">
                                            {{ match ($state) {
                                                'active' => 'Running',
                                                'activating' => 'Starting…',
                                                'inactive' => 'Waiting…',
                                                'failed' => 'Failed',
                                                default => ucfirst($state),
                                            } }}
                                        </x-badge>
                                    </li>
                                @endforeach
                            </ul>

                            @if(in_array('failed', $this->serviceStates, true))
                                <x-alert type="error" class="mt-4">
                                    A service failed to start. See why with <code>journalctl --user -u SERVICE</code> on the host,
                                    fix it, then try again.
                                </x-alert>

                                <x-button wire:click="installServices" variant="neutral" target="installServices" class="mt-4 self-start">
                                    Try again
                                </x-button>
                            @endif
                        @endif
                    @endif

                    @error('services')
                        <x-alert type="error" class="mt-4">{{ $message }}</x-alert>
                    @enderror
                @endif

                @if($this->step->type === \App\Installer\StepType::Secrets)
                    @if($this->requiredSecrets === null)
                        <x-alert type="warning" class="mt-4">
                            No workspace is mounted, so the installer can't read which secrets the services need.
                            Start it again with the app's folder mounted at <code>/workspace</code>.
                        </x-alert>
                    @else
                        @if($this->requiredSecrets === [] && ! $errors->has('secrets'))
                            <x-alert type="success" class="mt-4">✓ These services don't use any Podman secrets.</x-alert>
                        @endif

                        <form wire:submit="createSecrets" class="mt-4 space-y-3">
                            @foreach($this->requiredSecrets as $required)
                                <x-card wire:key="secret-{{ $required['secret']->name }}" class="card-sm text-sm">
                                    <p class="flex items-center justify-between gap-4">
                                        <code class="break-all">{{ $required['secret']->name }}</code>
                                        <x-badge :type="$required['exists'] ? 'success' : 'ghost'">
                                            {{ $required['exists'] ? 'Exists' : 'Missing' }}
                                        </x-badge>
                                    </p>
                                    <p class="text-base-content/60">
                                        For {{ implode(', ', $required['secret']->targets) }}. {{ $required['source'] }}.
                                    </p>

                                    @if($required['input'] && (! $required['exists'] || $replaceSecrets))
                                        <input type="password" wire:model="secretValues.{{ $required['secret']->name }}" autocomplete="new-password"
                                            aria-label="{{ $required['secret']->name }}" placeholder="Leave empty to generate one"
                                            class="input mt-1 w-full">
                                    @endif
                                </x-card>
                            @endforeach

                            @if(collect($this->requiredSecrets)->contains('exists', true))
                                <label class="label gap-2 text-sm text-base-content">
                                    <input type="checkbox" wire:model.live="replaceSecrets" class="checkbox checkbox-sm">
                                    Replace the secrets that exist already
                                </label>
                            @endif

                            @if($this->requiredSecrets !== [] && (! $this->canContinue || $replaceSecrets))
                                <div>
                                    <x-button type="submit" variant="neutral" target="createSecrets" loading="Creating…">
                                        Create secrets
                                    </x-button>
                                </div>
                            @endif
                        </form>
                    @endif

                    @error('secrets')
                        <x-alert type="error" class="mt-4">{{ $message }}</x-alert>
                    @enderror
                @endif

                <nav class="card-actions mt-6 justify-between">
                    <x-button wire:click="previous" variant="ghost" :disabled="$current === 0">
                        Back
                    </x-button>

                    @if($current < count($this->manifest->steps) - 1)
                        <div class="flex gap-2">
                            @if($this->canSkip && ! $this->canContinue)
                                <x-button wire:click="skip" variant="ghost">
                                    Skip step
                                </x-button>
                            @endif

                            <x-button wire:click="next" variant="primary" :disabled="! $this->canContinue">
                                Continue
                            </x-button>
                        </div>
                    @endif
                </nav>
            </x-card>
        </div>
    </main>

    <footer class="footer footer-center p-4 text-sm text-base-content/60">
        <p>
            Made with <a href="https://github.com/foxws/laravel-installer" target="_blank" rel="noopener noreferrer" class="link">laravel-installer</a>
        </p>
    </footer>
</div>
