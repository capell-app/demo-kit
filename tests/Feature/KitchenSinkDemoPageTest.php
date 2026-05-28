<?php

declare(strict_types=1);

use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\DemoKit\Actions\InstallKitchenSinkDemoPageAction;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;

it('installs the kitchen sink demo page idempotently', function (): void {
    $firstPage = InstallKitchenSinkDemoPageAction::run();
    $secondPage = InstallKitchenSinkDemoPageAction::run();

    $layout = Layout::query()->firstWhere('key', 'kitchen-sink-demo');

    expect($secondPage->getKey())->toBe($firstPage->getKey())
        ->and(Page::query()->where('name', 'Kitchen Sink Demo Page')->count())->toBe(1)
        ->and($layout)->not->toBeNull()
        ->and($layout->containers['main']['widgets'])->toHaveCount(7)
        ->and(WidgetAsset::query()->where('pageable_id', $secondPage->getKey())->count())->toBe(7);
});

it('stores one page h1 and all forty reference section headings', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run();
    $translation = $page->translations()->first();

    expect(substr_count((string) $translation?->content, '<h1>'))->toBe(1);

    $headings = collect($page->layout?->containers['main']['widgets'] ?? [])
        ->pluck('widget_key')
        ->map(fn (string $key) => Widget::query()->firstWhere('key', $key)?->meta['sections'] ?? [])
        ->flatten(1)
        ->pluck('heading')
        ->values()
        ->all();

    expect($headings)->toBe(InstallKitchenSinkDemoPageAction::sectionHeadings());
});
