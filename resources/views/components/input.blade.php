@props([
    'label' => null,
    'error' => null,
])

<fieldset class="fieldset">
    @if($label)
        <label for="{{ $attributes->get('id') }}" class="fieldset-legend">{{ $label }}</label>
    @endif

    <input {{ $attributes->class(['input w-full', 'input-error' => $error && $errors->has($error)]) }}>

    @if($error)
        @error($error)
            <p class="label text-error">{{ $message }}</p>
        @enderror
    @endif
</fieldset>
