<div {{ $attributes->class(['mockup-code w-full text-sm']) }}>
    @foreach(explode("\n", trim($slot->toHtml(), "\n")) as $line)
        <pre><code>{!! $line !!}</code></pre>
    @endforeach
</div>
