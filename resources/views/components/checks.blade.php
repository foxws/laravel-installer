@props([
    'checks',
])

<ul {{ $attributes->class(['space-y-3']) }}>
    @foreach($checks as $check)
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
