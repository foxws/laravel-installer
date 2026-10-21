@props([
    'variant' => null,
    'type' => 'button',
    'target' => null,
    'loading' => null,
])

<button type="{{ $type }}"
    {{ $attributes->class([
        'btn',
        'btn-primary' => $variant === 'primary',
        'btn-neutral' => $variant === 'neutral',
        'btn-ghost' => $variant === 'ghost',
    ]) }}
    @if($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @endif>
    @if($target)
        <span wire:loading.remove wire:target="{{ $target }}">{{ $slot }}</span>
        <span wire:loading wire:target="{{ $target }}" class="loading loading-spinner loading-sm"></span>
        <span wire:loading wire:target="{{ $target }}">{{ $loading ?? $slot }}</span>
    @else
        {{ $slot }}
    @endif
</button>
