<?php

declare(strict_types=1);

it('marks the done and current steps', function () {
    $view = $this->blade('<x-steps :steps="$steps" :current="1" />', ['steps' => ['Welcome', 'Requirements', 'Services']]);

    $view->assertSeeInOrder(['step step-primary', 'Welcome', 'step step-primary', 'aria-current="step"', 'Requirements'], false);
    $view->assertSee('<li wire:key="step-2" class="step">', false);
});

it('disables the button and shows a spinner while its target runs', function () {
    $view = $this->blade('<x-button type="submit" target="save" loading="Saving…">Save</x-button>');

    $view->assertSee('type="submit"', false);
    $view->assertSee('wire:loading.attr="disabled" wire:target="save"', false);
    $view->assertSee('loading-spinner', false);
    $view->assertSeeInOrder(['Save', 'Saving…']);
});

it('renders a plain button without loading states', function () {
    $view = $this->blade('<x-button wire:click="next" :disabled="true">Continue</x-button>');

    $view->assertSee('type="button"', false);
    $view->assertSee('wire:click="next"', false);
    $view->assertSee('disabled', false);
    $view->assertDontSee('wire:loading', false);
});

it('puts each line of code in its own pre, still escaped', function () {
    $view = $this->blade('<x-code>{{ $output }}</x-code>', ['output' => "echo '<b>'\nsudo sysctl --system\n"]);

    $view->assertSeeInOrder(['<pre><code>echo &#039;&lt;b&gt;&#039;</code></pre>', '<pre><code>sudo sysctl --system</code></pre>'], false);
    $view->assertDontSee('<b>', false);
});
