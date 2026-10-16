<main class="mx-auto max-w-2xl px-4 py-10">
    <header class="mb-8">
        <p class="text-sm text-zinc-500">{{ $this->manifest->name }} installer</p>

        <ol class="mt-3 flex flex-wrap gap-2 text-sm">
            @foreach ($this->manifest->steps as $index => $step)
                <li @class([
                    'rounded-full px-3 py-1',
                    'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $index === $current,
                    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $index !== $current,
                ]) @if ($index === $current) aria-current="step" @endif>
                    {{ $step->title }}
                </li>
            @endforeach
        </ol>
    </header>

    <h1 class="text-2xl font-semibold">{{ $this->step->title }}</h1>

    <div class="step-content mt-4">
        {!! $this->step->html !!}
    </div>

    @if ($this->step->type === \App\Installer\StepType::Requirements)
        <ul class="mt-6 space-y-3">
            @foreach ($this->report->checks as $check)
                <li wire:key="check-{{ $check->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="flex gap-2">
                        <span @class([
                            'font-bold',
                            'text-green-600' => $check->status === \App\Installer\CheckStatus::Ok,
                            'text-amber-600' => $check->status === \App\Installer\CheckStatus::Warn,
                            'text-red-600' => $check->status === \App\Installer\CheckStatus::Fail,
                            'text-zinc-500' => $check->status === \App\Installer\CheckStatus::Unknown,
                        ])>{{ match ($check->status) {
                            \App\Installer\CheckStatus::Ok => '✓',
                            \App\Installer\CheckStatus::Warn => '!',
                            \App\Installer\CheckStatus::Fail => '✗',
                            \App\Installer\CheckStatus::Unknown => '?',
                        } }}</span>
                        <span>{{ $check->message }}</span>
                    </p>

                    @if ($check->fix !== '')
                        <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-sm dark:bg-zinc-900"><code>{{ $check->fix }}</code></pre>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($this->report->hasFailures())
            <p class="mt-6 text-red-700 dark:text-red-300">
                Fix the problems above on the host, then start the installer again.
            </p>
        @endif
    @endif

    @if ($this->step->type === \App\Installer\StepType::Command)
        @if (in_array($this->step->id, $completed, true))
            <p class="mt-6 font-medium text-green-700 dark:text-green-400">✓ Done.</p>
        @else
            <form wire:submit="runCommand" class="mt-6 space-y-4">
                @foreach ($this->step->inputs as $input)
                    <div wire:key="input-{{ $input->name }}">
                        <label for="input-{{ $input->name }}" class="block text-sm font-medium">{{ $input->label }}</label>
                        <input id="input-{{ $input->name }}" type="{{ $input->type->value }}" wire:model="values.{{ $input->name }}"
                            autocomplete="{{ $input->secret ? 'new-password' : 'off' }}"
                            class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                        @error("values.{$input->name}")
                            <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button type="submit" wire:loading.attr="disabled"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-white disabled:opacity-40 dark:bg-white dark:text-zinc-900">
                    <span wire:loading.remove wire:target="runCommand">Run</span>
                    <span wire:loading wire:target="runCommand">Running…</span>
                </button>
            </form>
        @endif

        @error('command')
            <p class="mt-4 text-red-700 dark:text-red-300">{{ $message }}</p>
        @enderror

        @if ($output)
            <pre class="mt-4 max-h-80 overflow-auto rounded bg-zinc-100 p-3 text-sm dark:bg-zinc-900"><code>{{ $output }}</code></pre>
        @endif
    @endif

    @if ($this->step->type === \App\Installer\StepType::Services)
        @if ($this->workspace === null)
            <p class="mt-6 rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                No workspace is mounted, so the installer can't install the services.
                Start it again with <code>lpod installer IMAGE --workspace PATH</code>.
            </p>
        @else
            <dl class="mt-6 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                <dt class="text-zinc-500">App</dt>
                <dd>{{ $this->workspace['name'] }} ({{ $this->workspace['url'] }})</dd>
                <dt class="text-zinc-500">Folder</dt>
                <dd><code>{{ $this->workspace['path'] }}</code></dd>
                <dt class="text-zinc-500">Presets</dt>
                <dd>{{ implode(', ', $this->workspace['presets']) }}</dd>
            </dl>

            @if ($this->serviceStates === [])
                <button type="button" wire:click="installServices" wire:loading.attr="disabled"
                    class="mt-6 rounded-lg bg-zinc-900 px-4 py-2 text-white disabled:opacity-40 dark:bg-white dark:text-zinc-900">
                    <span wire:loading.remove wire:target="installServices">Install and start</span>
                    <span wire:loading wire:target="installServices">Installing…</span>
                </button>
            @else
                <ul class="mt-6 space-y-2" @unless ($this->canContinue) wire:poll.3s="refreshServices" @endunless>
                    @foreach ($this->serviceStates as $service => $state)
                        <li wire:key="service-{{ $service }}" class="flex justify-between gap-4 rounded-lg border border-zinc-200 px-4 py-2 text-sm dark:border-zinc-800">
                            <code>{{ $service }}</code>
                            <span @class([
                                'text-green-600' => $state === 'active',
                                'text-red-600' => $state === 'failed',
                                'text-zinc-500' => ! in_array($state, ['active', 'failed'], true),
                            ])>{{ match ($state) {
                                'active' => 'Running',
                                'activating' => 'Starting…',
                                'inactive' => 'Waiting…',
                                'failed' => 'Failed',
                                default => ucfirst($state),
                            } }}</span>
                        </li>
                    @endforeach
                </ul>

                @if (in_array('failed', $this->serviceStates, true))
                    <p class="mt-4 text-red-700 dark:text-red-300">
                        A service failed to start. See why with <code>journalctl --user -u SERVICE</code> on the host,
                        fix it, then try again.
                    </p>

                    <button type="button" wire:click="installServices" wire:loading.attr="disabled"
                        class="mt-4 rounded-lg bg-zinc-900 px-4 py-2 text-white disabled:opacity-40 dark:bg-white dark:text-zinc-900">
                        Try again
                    </button>
                @endif
            @endif
        @endif

        @error('services')
            <p class="mt-4 text-red-700 dark:text-red-300">{{ $message }}</p>
        @enderror
    @endif

    @if ($this->step->type === \App\Installer\StepType::Secrets)
        @if ($this->requiredSecrets === null)
            <p class="mt-6 rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                No workspace is mounted, so the installer can't read which secrets the services need.
                Start it again with the app's folder mounted at <code>/workspace</code>.
            </p>
        @else
            <form wire:submit="createSecrets" class="mt-6 space-y-3">
                @foreach ($this->requiredSecrets as $required)
                    <div wire:key="secret-{{ $required['secret']->name }}" class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                        <p class="flex justify-between gap-4">
                            <code>{{ $required['secret']->name }}</code>
                            <span @class([
                                'text-green-600' => $required['exists'],
                                'text-zinc-500' => ! $required['exists'],
                            ])>{{ $required['exists'] ? 'Exists' : 'Missing' }}</span>
                        </p>
                        <p class="mt-1 text-zinc-500">
                            For {{ implode(', ', $required['secret']->targets) }}. {{ $required['source'] }}.
                        </p>

                        @if ($required['input'] && (! $required['exists'] || $replaceSecrets))
                            <input type="password" wire:model="secretValues.{{ $required['secret']->name }}" autocomplete="new-password"
                                aria-label="{{ $required['secret']->name }}" placeholder="Leave empty to generate one"
                                class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                        @endif
                    </div>
                @endforeach

                @if (collect($this->requiredSecrets)->contains('exists', true))
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="replaceSecrets">
                        Replace the secrets that exist already
                    </label>
                @endif

                @if ($this->requiredSecrets !== [] && (! $this->canContinue || $replaceSecrets))
                    <button type="submit" wire:loading.attr="disabled"
                        class="rounded-lg bg-zinc-900 px-4 py-2 text-white disabled:opacity-40 dark:bg-white dark:text-zinc-900">
                        <span wire:loading.remove wire:target="createSecrets">Create secrets</span>
                        <span wire:loading wire:target="createSecrets">Creating…</span>
                    </button>
                @endif
            </form>
        @endif

        @error('secrets')
            <p class="mt-4 text-red-700 dark:text-red-300">{{ $message }}</p>
        @enderror
    @endif

    <nav class="mt-10 flex justify-between">
        <button type="button" wire:click="previous" @disabled($current === 0)
            class="rounded-lg px-4 py-2 text-zinc-700 disabled:opacity-40 dark:text-zinc-300">
            Back
        </button>

        @if ($current < count($this->manifest->steps) - 1)
            <button type="button" wire:click="next" @disabled(! $this->canContinue)
                class="rounded-lg bg-zinc-900 px-4 py-2 text-white disabled:opacity-40 dark:bg-white dark:text-zinc-900">
                Continue
            </button>
        @endif
    </nav>
</main>
