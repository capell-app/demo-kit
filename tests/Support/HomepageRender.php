<?php

declare(strict_types=1);

use Capell\LayoutBuilder\Models\Widget;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Blaze\Blaze;

require_once __DIR__ . '/../../../theme-foundation/tests/Support/WidgetViewFixture.php';
require_once __DIR__ . '/../../../theme-foundation/tests/Support/DomQueries.php';

function renderHomepageWidget(Widget $widget): string
{
    $prepared = foundationWidgetViewFixture(is_array($widget->meta) ? $widget->meta : [], count: 0);
    $prepared->key = $widget->key;
    view()->addNamespace('capell-hero', dirname(__DIR__, 3) . '/hero/resources/views');
    view()->addNamespace('capell-demo-kit', dirname(__DIR__, 2) . '/resources/views');
    $wasBlazeEnabled = Blaze::isEnabled();
    Blaze::disable();
    try {
        return view('capell-demo-kit::components.widget.homepage-section', [
            'widget' => $prepared, 'container' => [], 'containerKey' => 'main', 'containerWidth' => null,
            'loop' => (object) ['index' => 0], 'attributes' => new ComponentAttributeBag,
        ])->render();
    } finally {
        if ($wasBlazeEnabled) {
            Blaze::enable();
        }
    }
}
