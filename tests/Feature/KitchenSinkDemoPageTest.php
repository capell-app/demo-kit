<?php

declare(strict_types=1);

use Capell\Core\Enums\ContainerWidthEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\DemoKit\Actions\InstallKitchenSinkDemoPageAction;
use Capell\Frontend\Support\CapellFrontendContext;
use Capell\Frontend\Support\State\FrontendState;
use Capell\LayoutBuilder\Actions\Fragments\RenderPublicFragmentAction;
use Capell\LayoutBuilder\Enums\WidgetTypeEnum;
use Capell\LayoutBuilder\Filament\Resources\Widgets\Pages\CreateWidget;
use Capell\LayoutBuilder\Filament\Resources\Widgets\Pages\EditWidget;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\LayoutBuilder\Support\LayoutBuilderAdminRegistrar;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(CreatesAdminUser::class);

function kitchenSinkRequiredLayout(?Layout $layout): Layout
{
    throw_unless($layout instanceof Layout, RuntimeException::class, 'Expected the kitchen sink layout to exist.');

    return $layout;
}

/**
 * @return array{widgets: array<int, array<string, mixed>>}
 */
function kitchenSinkMainContainer(Layout $layout): array
{
    $containers = $layout->containers;
    $main = is_array($containers) ? ($containers['main'] ?? null) : null;
    $widgets = is_array($main) ? ($main['widgets'] ?? null) : null;

    throw_unless(is_array($widgets), RuntimeException::class, 'Expected the kitchen sink layout to have main widgets.');

    return ['widgets' => $widgets];
}

it('installs the kitchen sink demo page idempotently', function (): void {
    $firstPage = InstallKitchenSinkDemoPageAction::run();
    $secondPage = InstallKitchenSinkDemoPageAction::run();

    $layout = kitchenSinkRequiredLayout(Layout::query()->firstWhere('key', 'kitchen-sink-demo'));

    expect($secondPage->getKey())->toBe($firstPage->getKey())
        ->and(Page::query()->where('name', 'Kitchen Sink Demo Page')->count())->toBe(1)
        ->and($layout)->not->toBeNull()
        ->and(kitchenSinkMainContainer($layout)['widgets'])->toHaveCount(7)
        ->and(WidgetAsset::query()->where('pageable_id', $secondPage->getKey())->count())->toBe(7);
});

it('stores lazy presentation metadata only on below fold kitchen sink layout instances', function (): void {
    InstallKitchenSinkDemoPageAction::run();

    $layout = kitchenSinkRequiredLayout(Layout::query()->firstWhere('key', 'kitchen-sink-demo'));
    $layoutWidgets = kitchenSinkMainContainer($layout)['widgets'];

    $widgets = collect($layoutWidgets)->keyBy('widget_key');

    expect($widgets->get('kitchen-sink-structured-text'))->not->toHaveKey('meta.presentation');

    foreach ([
        'kitchen-sink-rich-text',
        'kitchen-sink-data-display',
        'kitchen-sink-interactions',
        'kitchen-sink-embeds',
        'kitchen-sink-forms',
        'kitchen-sink-utility-states',
    ] as $widgetKey) {
        expect($widgets->get($widgetKey)['meta']['presentation'] ?? null)->toBe([
            'delivery_mode' => 'lazy_fragment',
            'loading_strategy' => 'visible',
        ]);
    }
});

it('stores one page h1 and all forty reference section headings', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run();
    $translation = $page->translations()->first();

    expect(substr_count((string) $translation?->content, '<h1>'))->toBe(1);

    $layoutContainers = $page->layout?->containers;
    expect(is_array($layoutContainers))->toBeTrue();

    $layoutWidgets = is_array($layoutContainers['main']) && is_array($layoutContainers['main']['widgets'] ?? null)
        ? $layoutContainers['main']['widgets']
        : [];

    $headings = collect($layoutWidgets)
        ->pluck('widget_key')
        ->map(fn (string $key) => Widget::query()->firstWhere('key', $key)?->meta['sections'] ?? [])
        ->flatten(1)
        ->pluck('heading')
        ->values()
        ->all();

    expect($headings)->toBe(InstallKitchenSinkDemoPageAction::sectionHeadings());
});

it('can edit every kitchen sink layout widget without losing demo creator data', function (): void {
    test()->actingAsAdmin();
    resolve(LayoutBuilderAdminRegistrar::class)->register();

    $page = InstallKitchenSinkDemoPageAction::run()->loadMissing(['layout', 'translations.language']);
    $language = $page->translations->first()?->language;

    expect($language)->not->toBeNull();

    $layout = $page->layout;
    expect($layout)->toBeInstanceOf(Layout::class);

    $layoutWidgets = is_array($layout->containers['main']['widgets'] ?? null)
        ? $layout->containers['main']['widgets']
        : [];

    expect($layoutWidgets)->toHaveCount(7);

    foreach ($layoutWidgets as $layoutWidget) {
        $widgetKey = $layoutWidget['widget_key'];
        $widget = Widget::query()
            ->with(['translations', 'assets', 'type'])
            ->firstWhere('key', $widgetKey);

        expect($widget)->toBeInstanceOf(Widget::class);
        assert($widget instanceof Widget);

        $originalMeta = $widget->meta ?? [];
        $originalSections = $originalMeta['sections'] ?? null;
        $originalFamily = $originalMeta['family'] ?? null;
        $originalComponent = $widget->component;
        $editedName = $widget->name . ' Edited';
        $editedFamily = $originalFamily . ' Edited';
        $editedSectionHeading = $originalSections[0]['heading'] . ' Edited';
        $editedSections = $originalSections;
        $editedSections[0]['heading'] = $editedSectionHeading;
        $originalAssetIds = $widget->assets->pluck('id')->sort()->values()->all();

        expect($widget->type?->key)->toBe(WidgetTypeEnum::KitchenSinkReference->value, $widgetKey)
            ->and($originalFamily)->toBeString($widgetKey)
            ->and($originalSections)->toBeArray($widgetKey)
            ->and($originalSections)->not->toBeEmpty($widgetKey);

        $existingTranslation = $widget->translations->firstWhere('language_id', $language?->getKey());
        $editedTitle = $widget->name . ' edited title';
        $editedContent = '<p>Edited kitchen sink widget content for ' . e($widget->key) . '.</p>';

        expect($existingTranslation)->not->toBeNull($widgetKey);

        Livewire::test(EditWidget::class, ['record' => $widget->getRouteKey()])
            ->assertSuccessful()
            ->fillForm([
                'name' => $editedName,
                'status' => (bool) $widget->status,
            ])
            ->set('data.translations.record-' . $existingTranslation?->getKey() . '.title', $editedTitle)
            ->set('data.translations.record-' . $existingTranslation?->getKey() . '.content', $editedContent)
            ->set('data.meta.family', $editedFamily)
            ->set('data.meta.sections', $editedSections)
            ->call('save')
            ->assertHasNoFormErrors();

        $editedWidget = $widget->refresh()->loadMissing(['translations', 'assets']);
        $editedMeta = $editedWidget->meta ?? [];

        expect($editedWidget->name)->toBe($editedName, $widgetKey)
            ->and($editedMeta['family'] ?? null)->toBe($editedFamily, $widgetKey)
            ->and($editedMeta['sections'][0]['heading'] ?? null)->toBe($editedSectionHeading, $widgetKey)
            ->and($editedMeta['sections'][0]['key'] ?? null)->toBe($originalSections[0]['key'] ?? null, $widgetKey)
            ->and($editedMeta['component'] ?? $editedWidget->component)->toBe($originalMeta['component'] ?? $originalComponent, $widgetKey)
            ->and($editedWidget->assets->pluck('id')->sort()->values()->all())->toBe($originalAssetIds, $widgetKey);

        $editedTranslation = $editedWidget->translations()
            ->where('language_id', $language?->getKey())
            ->first();

        expect($editedTranslation?->title)->toBe($editedTitle, $widgetKey)
            ->and($editedTranslation?->content)->toBe($editedContent, $widgetKey);
    }
});

it('can manually create kitchen sink reference widgets through Filament', function (): void {
    test()->actingAsAdmin();
    resolve(LayoutBuilderAdminRegistrar::class)->register();

    InstallKitchenSinkDemoPageAction::run();

    $type = Blueprint::query()
        ->where('type', 'widget')
        ->where('key', WidgetTypeEnum::KitchenSinkReference->value)
        ->firstOrFail();
    $key = 'manual-kitchen-sink-reference-' . Str::random(8);
    $sections = [
        [
            'key' => 'manual-reference',
            'heading' => 'Manual reference',
            'summary' => 'A manually created kitchen sink reference section.',
        ],
    ];

    Livewire::test(CreateWidget::class)
        ->assertSuccessful()
        ->set('data.blueprint_id', $type->getKey())
        ->set('data.translations', [])
        ->fillForm([
            'name' => 'Manual kitchen sink reference',
            'key' => $key,
            'blueprint_id' => $type->getKey(),
            'status' => true,
            'meta' => [
                'family' => 'Manual reference',
                'sections' => $sections,
            ],
            'translations' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $widget = Widget::query()->firstWhere('key', $key);

    expect($widget)->toBeInstanceOf(Widget::class);
    assert($widget instanceof Widget);

    expect($widget->blueprint_id)->toBe($type->getKey())
        ->and($widget->meta['family'] ?? null)->toBe('Manual reference')
        ->and($widget->meta['sections'] ?? null)->toBe($sections);
});

it('renders the structured text widget eagerly and lazy placeholders for below fold sections', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run();
    $html = kitchenSinkLayoutHtml($page);

    expect(substr_count($html, '<h1>'))->toBe(1)
        ->and($html)->toContain('Kitchen Sink Demo Page')
        ->and($html)->toContain('Hero')
        ->and($html)->toContain('Breadcrumbs')
        ->and($html)->toContain('Table of contents')
        ->and(preg_match_all('/\sdata-deferred-fragment(\s|>)/', $html))->toBe(6)
        ->and($html)->not->toContain('data-capell-fragment')
        ->and($html)->not->toContain('_capell/fragments')
        ->and($html)->not->toContain('capell-layout-builder')
        ->and($html)->not->toContain('Paragraph styles')
        ->and($html)->not->toContain('Full form')
        ->and($html)->not->toContain('kitchen-sink-rich-text')
        ->and($html)->not->toContain('capell-app')
        ->and($html)->not->toContain('data-field-path')
        ->and($html)->not->toContain('data-capell-authoring')
        ->and($html)->not->toContain('signed');
});

it('fetches each lazy kitchen sink fragment through the public fragment route', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run();
    $html = kitchenSinkLayoutHtml($page);
    preg_match_all('/data-deferred-fragment-url="([^"]+)"/', $html, $matches);

    expect($matches[1])->toHaveCount(6);

    $fragmentHtml = '';

    foreach ($matches[1] as $fragmentUrl) {
        $path = (string) parse_url(html_entity_decode($fragmentUrl), PHP_URL_PATH);

        $this->get($path)->assertOk();

        $reference = rawurldecode((string) str($path)->after('/_fragments/'));
        $fragmentHtml .= RenderPublicFragmentAction::run($reference);
    }

    expect($html . $fragmentHtml)->toContain('Hero')
        ->and($html . $fragmentHtml)->toContain('Footer')
        ->and(kitchenSinkHeadingsFromHtml($html . $fragmentHtml))->toBe(InstallKitchenSinkDemoPageAction::sectionHeadings())
        ->and($fragmentHtml)->toContain('FAQ accordion')
        ->and($fragmentHtml)->toContain('role="tablist"')
        ->and($fragmentHtml)->toContain('aria-expanded="false"')
        ->and($fragmentHtml)->toContain('<table>')
        ->and($fragmentHtml)->toContain('<form')
        ->and($fragmentHtml)->toContain('aria-labelledby');
});

function kitchenSinkLayoutHtml(Page $page): string
{
    $page->loadMissing(['layout', 'site', 'translations.language']);
    $language = $page->translations->first()?->language;
    $site = $page->site;
    $layout = $page->layout;

    throw_if(! $site instanceof Site || ! $layout instanceof Layout || ! $language instanceof Language, RuntimeException::class, 'Expected kitchen sink page context to be loaded.');

    app()->instance(
        CapellFrontendContext::class,
        new CapellFrontendContext(
            (new FrontendState)
                ->withSite($site)
                ->withLanguage($language)
                ->withPage($page)
                ->withLayout($layout),
        ),
    );

    $container = kitchenSinkMainContainer($layout);
    $translation = $page->translations->first();
    $html = (string) $translation->content;

    foreach ($container['widgets'] as $widgetIndex => $widgetData) {
        $widget = Widget::query()->firstWhere('key', $widgetData['widget_key']);

        if (! $widget instanceof Widget) {
            continue;
        }

        $html .= view('capell-layout-builder::components.layout.widget', [
            'component' => $widget->getComponent(),
            'containerColspan' => 12,
            'container' => $container,
            'containerKey' => 'main',
            'containerIndex' => 0,
            'containerWidth' => ContainerWidthEnum::Full,
            'loop' => (object) ['index' => $widgetIndex],
            'layout' => $page->layout,
            'type' => $widget->getMetaComponentType(),
            'widget' => $widget,
            'widgetIndex' => $widgetIndex,
            'widgetData' => $widgetData,
            'pageSlot' => null,
        ])->render();
    }

    return $html;
}

/**
 * @return array<int, string>
 */
function kitchenSinkHeadingsFromHtml(string $html): array
{
    preg_match_all('/<h2[^>]*>(.*?)<\/h2>/s', $html, $matches);

    return collect($matches[1])->map(fn (string $heading): string => trim(strip_tags($heading)))->values()->all();
}
