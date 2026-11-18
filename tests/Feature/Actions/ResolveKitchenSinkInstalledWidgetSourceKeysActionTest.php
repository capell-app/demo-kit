<?php

declare(strict_types=1);

use Capell\DemoKit\Actions\ResolveKitchenSinkInstalledWidgetSourceKeysAction;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;

function kitchenSinkInstalledWidget(string $key, string $component, bool $active = true): void
{
    Widget::query()->create([
        'key' => $key,
        'name' => $key,
        'blueprint_id' => resolve(TypeCreator::class)->defaultWidgetType()->getKey(),
        'component' => $component,
        'status' => $active,
    ]);
}

it('adds one installed widget for each component the fixed sources miss', function (): void {
    kitchenSinkInstalledWidget('breadcrumbs', 'capell.widget.page.breadcrumbs');
    kitchenSinkInstalledWidget('crumbs-copy', 'capell.widget.page.breadcrumbs');
    kitchenSinkInstalledWidget('foundation-hero-b', 'capell.widget.foundation.hero');
    kitchenSinkInstalledWidget('foundation-hero-a', 'capell.widget.foundation.hero');
    kitchenSinkInstalledWidget('blog-archives', 'capell-blog::widget.page.archives');
    kitchenSinkInstalledWidget('disabled-widget', 'capell.widget.disabled');
    kitchenSinkInstalledWidget('kitchen-sink-007-generated', 'capell.widget.generated-only');

    Widget::query()->where('key', 'disabled-widget')->update(['status' => false]);

    expect(ResolveKitchenSinkInstalledWidgetSourceKeysAction::run(['breadcrumbs']))
        ->toBe(['blog-archives', 'foundation-hero-a']);
});

it('adds nothing when every installed component is already covered', function (): void {
    kitchenSinkInstalledWidget('breadcrumbs', 'capell.widget.page.breadcrumbs');

    expect(ResolveKitchenSinkInstalledWidgetSourceKeysAction::run(['breadcrumbs']))->toBe([]);
});
