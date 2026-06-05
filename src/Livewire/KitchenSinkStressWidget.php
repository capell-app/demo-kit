<?php

declare(strict_types=1);

namespace Capell\DemoKit\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

final class KitchenSinkStressWidget extends Component
{
    public string $widgetReference = '';

    public int $interactionCount = 0;

    public function increment(): void
    {
        $this->interactionCount++;
    }

    public function render(): View
    {
        return view('capell-demo-kit::livewire.kitchen-sink-stress-widget', [
            'referenceLength' => strlen($this->widgetReference),
        ]);
    }
}
