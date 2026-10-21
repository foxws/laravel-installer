@props([
    'type' => 'ghost',
    'spinner' => false,
])

<span {{ $attributes->class([
    'badge badge-soft',
    'badge-success' => $type === 'success',
    'badge-error' => $type === 'error',
    'badge-ghost' => $type === 'ghost',
]) }}>
    @if($spinner)
        <span class="loading loading-spinner loading-xs"></span>
    @endif
    {{ $slot }}
</span>
