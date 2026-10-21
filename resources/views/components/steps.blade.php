@props([
    'steps',
    'current',
])

<div {{ $attributes->class(['overflow-x-auto']) }}>
    <ul class="steps min-w-full">
        @foreach($steps as $index => $title)
            <li wire:key="step-{{ $index }}" @class(['step', 'step-primary' => $index <= $current])@if($index === $current) aria-current="step"@endif>{{ $title }}</li>
        @endforeach
    </ul>
</div>
