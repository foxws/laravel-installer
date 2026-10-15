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
        @unless ($this->report->fromHost)
            <p class="mt-6 rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                These checks come from what the installer can see through the Podman socket.
                For a full check, run <code>lpod doctor</code> on the host, or start the installer with <code>lpod installer</code>.
            </p>
        @endunless

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
