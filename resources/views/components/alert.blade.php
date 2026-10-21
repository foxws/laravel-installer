@props([
    'type' => 'info',
    'fix' => null,
])

<div role="alert" {{ $attributes->class([
    'alert alert-soft items-start',
    'alert-success' => $type === 'success',
    'alert-warning' => $type === 'warning',
    'alert-error' => $type === 'error',
    'alert-info' => $type === 'info',
]) }}>
    <div class="min-w-0 space-y-2">
        <div>{{ $slot }}</div>

        @if($fix?->isNotEmpty())
            <x-code>{{ $fix }}</x-code>
        @endif
    </div>
</div>
