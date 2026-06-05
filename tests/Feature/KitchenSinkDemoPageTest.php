<?php

declare(strict_types=1);

use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Enums\ContainerWidthEnum;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Enums\PageTypeEnum;
use Capell\Core\Enums\PresentationDeliveryMode;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Models\Theme;
use Capell\Core\Support\Creator\PageCreator;
use Capell\DemoKit\Actions\InstallKitchenSinkDemoPageAction;
use Capell\FoundationTheme\View\Components\Footer\LatestPages;
use Capell\Frontend\Facades\Frontend;
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
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    config()->set('capell-demo-kit.kitchen_sink.target_widget_count', 12);
    config()->set('capell-demo-kit.kitchen_sink.eager_widget_limit', 11);
    config()->set('capell-demo-kit.kitchen_sink.context_page_count', 4);
    config()->set('capell-demo-kit.kitchen_sink.context_asset_limit', 4);
});

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

function kitchenSinkExpectedLayoutWidgetCount(): int
{
    return count(InstallKitchenSinkDemoPageAction::layoutWidgetKeys());
}

function kitchenSinkEagerWidgetLimit(): int
{
    return InstallKitchenSinkDemoPageAction::eagerWidgetLimit();
}

function kitchenSinkExpectedContextPageCount(): int
{
    return InstallKitchenSinkDemoPageAction::contextPageCount();
}

function kitchenSinkExpectedContextAssetLimit(): int
{
    return InstallKitchenSinkDemoPageAction::contextAssetLimit();
}

function kitchenSinkUseReferenceMatrix(): void
{
    config()->set('capell-demo-kit.kitchen_sink.target_widget_count', 32);
    config()->set('capell-demo-kit.kitchen_sink.eager_widget_limit', 12);
    config()->set('capell-demo-kit.kitchen_sink.context_page_count', 8);
    config()->set('capell-demo-kit.kitchen_sink.context_asset_limit', 6);
}

it('installs the kitchen sink demo page idempotently', function (): void {
    $firstPage = InstallKitchenSinkDemoPageAction::run();
    $secondPage = InstallKitchenSinkDemoPageAction::run();

    $layout = kitchenSinkRequiredLayout(Layout::query()->firstWhere('key', 'kitchen-sink-demo'));
    $secondPage->loadMissing(['children.translation', 'pageUrl', 'siblings']);

    expect($secondPage->getKey())->toBe($firstPage->getKey())
        ->and(Page::query()->where('name', 'Kitchen Sink Demo Page')->count())->toBe(1)
        ->and($secondPage->parent_id)->toBeNull()
        ->and($secondPage->pageUrl?->url)->toBe('/kitchen-sink-showcase')
        ->and($secondPage->children)->toHaveCount(kitchenSinkExpectedContextPageCount())
        ->and($layout)->not->toBeNull()
        ->and(kitchenSinkMainContainer($layout)['widgets'])->toHaveCount(kitchenSinkExpectedLayoutWidgetCount())
        ->and(WidgetAsset::query()->where('pageable_id', $secondPage->getKey())->count())->toBeGreaterThan(kitchenSinkExpectedLayoutWidgetCount())
        ->and(SiteDomain::query()
            ->where('site_id', $secondPage->site_id)
            ->where('language_id', $secondPage->pageUrl?->language_id)
            ->exists())->toBeTrue();

    $secondPage->children->each(function (Page $childPage): void {
        expect($childPage->getMedia(MediaCollectionEnum::Image->value))->not->toBeEmpty($childPage->name);
    });

    $footer = new LatestPages(headingClass: 'font-semibold', pages: $secondPage->children);

    expect($footer->pages)->toBeEmpty();
});

it('installs the kitchen sink demo page on the default site and primary language', function (): void {
    $primaryLanguage = Language::factory()->create(['code' => 'de']);
    $secondaryLanguage = Language::factory()->english()->create();
    $site = Site::factory()
        ->language($primaryLanguage)
        ->default()
        ->withTranslations(collect([$primaryLanguage, $secondaryLanguage]))
        ->create(['name' => 'Capell Services']);

    $page = InstallKitchenSinkDemoPageAction::run()->loadMissing(['pageUrl', 'site']);

    expect($page->site->is($site))->toBeTrue()
        ->and($page->pageUrl?->language_id)->toBe($primaryLanguage->getKey())
        ->and($page->pageUrl?->url)->toBe('/kitchen-sink-showcase')
        ->and(Page::query()->where('name', 'Kitchen Sink Demo Page')->where('site_id', $site->getKey())->count())->toBe(1)
        ->and(Site::query()->where('name', 'Kitchen Sink Demo')->exists())->toBeFalse()
        ->and(Site::query()->count())->toBe(1);
});

it('promotes legacy nested kitchen sink installs to the showcase url', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()
        ->language($language)
        ->default()
        ->withTranslations(collect([$language]))
        ->create(['name' => 'Capell Services']);
    $layout = Layout::factory()->create([
        'key' => 'kitchen-sink-demo',
        'containers' => ['main' => ['widgets' => []]],
        'status' => true,
    ]);

    /** @var Page $legacyParent */
    $legacyParent = resolve(PageCreator::class)->createPage([
        'name' => 'Kitchen Sink Showcase',
        'layout_id' => $layout->getKey(),
        'type_key' => PageTypeEnum::Default,
        'meta' => ['demo_fixture' => 'kitchen-sink-parent'],
        'translations' => [
            'en' => [
                'title' => 'Kitchen Sink Showcase',
                'content' => '<p>Legacy overview.</p>',
                'summary' => 'Legacy overview.',
                'meta' => ['slug' => 'kitchen-sink-showcase'],
            ],
        ],
    ], $site, collect([$language]));
    SetupPageUrlsAction::run($legacyParent);

    /** @var Page $legacyPage */
    $legacyPage = resolve(PageCreator::class)->createPage([
        'name' => 'Kitchen Sink Demo Page',
        'layout_id' => $layout->getKey(),
        'type_key' => PageTypeEnum::Default,
        'parent_id' => $legacyParent->getKey(),
        'meta' => ['demo_fixture' => 'kitchen-sink'],
        'translations' => [
            'en' => [
                'title' => 'Kitchen Sink Demo Page',
                'content' => '<p>Legacy nested demo.</p>',
                'summary' => 'Legacy nested demo.',
                'meta' => ['slug' => 'kitchen-sink-demo'],
            ],
        ],
    ], $site, collect([$language]));
    SetupPageUrlsAction::run($legacyPage);

    $page = InstallKitchenSinkDemoPageAction::run($site)->loadMissing(['children', 'pageUrl']);

    expect($page->getKey())->toBe($legacyParent->getKey())
        ->and($page->name)->toBe('Kitchen Sink Demo Page')
        ->and($page->parent_id)->toBeNull()
        ->and($page->pageUrl?->url)->toBe('/kitchen-sink-showcase')
        ->and($page->children)->toHaveCount(kitchenSinkExpectedContextPageCount())
        ->and(Page::query()->whereKey($legacyPage->getKey())->exists())->toBeFalse()
        ->and(PageUrl::query()->where('url', '/kitchen-sink-showcase/kitchen-sink-demo')->exists())->toBeFalse()
        ->and(PageUrl::query()->withTrashed()->where('url', '/kitchen-sink-showcase/kitchen-sink-demo')->whereNotNull('deleted_at')->exists())->toBeTrue();
});

it('repairs missing site domains for an existing kitchen sink site', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->language($language)->create(['name' => 'Kitchen Sink Demo']);

    expect(SiteDomain::query()->where('site_id', $site->getKey())->exists())->toBeFalse();

    $page = InstallKitchenSinkDemoPageAction::run($site)->loadMissing('pageUrl');

    expect(SiteDomain::query()
        ->where('site_id', $site->getKey())
        ->where('language_id', $page->pageUrl?->language_id)
        ->exists())->toBeTrue();
});

it('stores lazy presentation metadata only on below fold kitchen sink layout instances', function (): void {
    InstallKitchenSinkDemoPageAction::run();

    $layout = kitchenSinkRequiredLayout(Layout::query()->firstWhere('key', 'kitchen-sink-demo'));
    $layoutWidgets = kitchenSinkMainContainer($layout)['widgets'];

    $eagerWidgets = collect($layoutWidgets)->take(kitchenSinkEagerWidgetLimit());
    $lazyWidgets = collect($layoutWidgets)->skip(kitchenSinkEagerWidgetLimit());
    $expectedLazyPresentation = [
        'delivery_mode' => PresentationDeliveryMode::LazyFragment->value,
        'loading_strategy' => 'visible',
    ];

    expect($layoutWidgets)->toHaveCount(kitchenSinkExpectedLayoutWidgetCount())
        ->and($eagerWidgets->filter(fn (array $widget): bool => isset($widget['meta']['presentation'])))->toHaveCount(0)
        ->and($lazyWidgets->filter(fn (array $widget): bool => ($widget['meta']['presentation'] ?? null) === $expectedLazyPresentation))->toHaveCount(kitchenSinkExpectedLayoutWidgetCount() - kitchenSinkEagerWidgetLimit())
        ->and(collect($layoutWidgets)->pluck('widget_key')->all())->toBe(InstallKitchenSinkDemoPageAction::layoutWidgetKeys());
});

it('installs custom heroes and livewire kitchen sink widgets in the matrix', function (): void {
    kitchenSinkUseReferenceMatrix();

    InstallKitchenSinkDemoPageAction::run();

    $layout = kitchenSinkRequiredLayout(Layout::query()->firstWhere('key', 'kitchen-sink-demo'));
    $layoutWidgets = collect(kitchenSinkMainContainer($layout)['widgets']);
    $heroWidgets = $layoutWidgets->filter(
        fn (array $widget): bool => str_contains((string) data_get($widget, 'meta.kitchen_sink.source_key'), 'hero'),
    );
    $livewireStressLayoutWidget = $layoutWidgets->first(
        fn (array $widget): bool => data_get($widget, 'meta.kitchen_sink.source_key') === 'kitchen-sink-livewire-stress',
    );
    $livewireLatestPagesLayoutWidget = $layoutWidgets->first(
        fn (array $widget): bool => data_get($widget, 'meta.kitchen_sink.source_key') === 'kitchen-sink-livewire-latest-pages',
    );

    throw_unless(is_array($livewireStressLayoutWidget), RuntimeException::class, 'Expected a Livewire stress layout widget.');
    throw_unless(is_array($livewireLatestPagesLayoutWidget), RuntimeException::class, 'Expected a Livewire latest pages layout widget.');

    $livewireStressWidget = Widget::query()->firstWhere('key', $livewireStressLayoutWidget['widget_key']);
    $livewireLatestPagesWidget = Widget::query()
        ->with('assets')
        ->firstWhere('key', $livewireLatestPagesLayoutWidget['widget_key']);

    expect($heroWidgets->count())->toBeGreaterThanOrEqual(3)
        ->and($livewireStressWidget)->toBeInstanceOf(Widget::class)
        ->and($livewireStressWidget?->is_livewire)->toBeTrue()
        ->and($livewireStressWidget?->component)->toBe('capell-demo-kit.widget.kitchen-sink-livewire-stress')
        ->and($livewireLatestPagesWidget)->toBeInstanceOf(Widget::class)
        ->and($livewireLatestPagesWidget?->is_livewire)->toBeTrue()
        ->and($livewireLatestPagesWidget?->component)->toBe('capell.widget.pages')
        ->and($livewireLatestPagesWidget?->assets)->toHaveCount(kitchenSinkExpectedContextAssetLimit());
});

it('stores one page h1 and all forty reference section headings', function (): void {
    kitchenSinkUseReferenceMatrix();

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
        ->unique()
        ->values()
        ->all();

    expect($headings)->toBe(InstallKitchenSinkDemoPageAction::sectionHeadings());
});

it('can edit a kitchen sink layout widget without losing demo creator data', function (): void {
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

    expect($layoutWidgets)->toHaveCount(kitchenSinkExpectedLayoutWidgetCount());

    $editedReferenceCount = 0;

    foreach ($layoutWidgets as $layoutWidget) {
        $widgetKey = $layoutWidget['widget_key'];
        $widget = Widget::query()
            ->with(['translations', 'assets', 'type'])
            ->firstWhere('key', $widgetKey);

        expect($widget)->toBeInstanceOf(Widget::class);
        assert($widget instanceof Widget);

        if ($widget->type?->key !== WidgetTypeEnum::KitchenSinkReference->value) {
            continue;
        }

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

        expect($widget->type->key)->toBe(WidgetTypeEnum::KitchenSinkReference->value, $widgetKey)
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

        $editedReferenceCount++;

        break;
    }

    expect($editedReferenceCount)->toBe(1);
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
    $layout = kitchenSinkRequiredLayout($page->layout);
    $deferredFragmentCount = collect(kitchenSinkMainContainer($layout)['widgets'])
        ->filter(fn (array $widget): bool => isset($widget['meta']['presentation']))
        ->count();

    expect(substr_count($html, '<h1>'))->toBe(1)
        ->and($html)->toContain('Kitchen Sink Demo Page')
        ->and($html)->toContain('Hero')
        ->and($html)->toContain('Breadcrumbs')
        ->and($html)->toContain('Table of contents')
        ->and(preg_match_all('/\sdata-deferred-fragment(\s|>)/', $html))->toBe($deferredFragmentCount)
        ->and($html)->not->toContain('data-capell-fragment')
        ->and($html)->not->toContain('_capell/fragments')
        ->and($html)->not->toContain('capell-layout-builder')
        ->and($html)->not->toContain('Paragraph styles')
        ->and($html)->not->toContain('Full form')
        ->and($html)->not->toContain('kitchen-sink-rich-text')
        ->and($html)->not->toContain('capell-app')
        ->and($html)->not->toContain('Layout Builder')
        ->and($html)->not->toContain('opaque widget reference')
        ->and($html)->not->toContain('hydration')
        ->and($html)->not->toContain('data-field-path')
        ->and($html)->not->toContain('data-capell-authoring')
        ->and($html)->not->toContain('signed');
});

it('fetches each lazy kitchen sink fragment through the public fragment route', function (): void {
    kitchenSinkUseReferenceMatrix();

    $page = InstallKitchenSinkDemoPageAction::run();
    $html = kitchenSinkLayoutHtml($page);
    preg_match_all('/data-deferred-fragment-url="([^"]+)"/', $html, $matches);
    $layout = kitchenSinkRequiredLayout($page->layout);
    $deferredFragmentCount = collect(kitchenSinkMainContainer($layout)['widgets'])
        ->filter(fn (array $widget): bool => isset($widget['meta']['presentation']))
        ->count();
    $deferredWidgetKeys = collect(kitchenSinkMainContainer($layout)['widgets'])
        ->filter(fn (array $widget): bool => isset($widget['meta']['presentation']))
        ->pluck('widget_key')
        ->values();

    expect($matches[1])->toHaveCount($deferredFragmentCount);

    $fragmentHtml = '';

    foreach ($matches[1] as $fragmentUrl) {
        $path = (string) parse_url(html_entity_decode($fragmentUrl), PHP_URL_PATH);

        $response = $this->get($path);

        $response->assertOk();

        $reference = rawurldecode((string) str($path)->after('/_fragments/'));
        $fragmentHtml .= RenderPublicFragmentAction::run($reference);
    }

    expect($html . $fragmentHtml)->toContain('Hero')
        ->and($html . $fragmentHtml)->toContain('Footer')
        ->and(kitchenSinkReferenceHeadingsFromHtml($html . $fragmentHtml))->toBe(InstallKitchenSinkDemoPageAction::sectionHeadings())
        ->and($fragmentHtml)->toContain('FAQ accordion')
        ->and($fragmentHtml)->toContain('role="tablist"')
        ->and($fragmentHtml)->toContain('aria-expanded="false"')
        ->and($fragmentHtml)->toContain('<table>')
        ->and($fragmentHtml)->toContain('<form')
        ->and($fragmentHtml)->toContain('aria-labelledby');
});

it('mounts kitchen sink pages widget assets and lazy fragments through public blade', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run();
    $bladeHtml = kitchenSinkLayoutHtml($page);

    expect($bladeHtml)->toContain('capell::widget.pages')
        ->and($bladeHtml)->toContain('data-deferred-fragment');

    $layout = kitchenSinkRequiredLayout($page->layout);
    $pagesCardWidget = collect(kitchenSinkMainContainer($layout)['widgets'])
        ->first(fn (array $widgetData): bool => data_get($widgetData, 'meta.kitchen_sink.source_key') === 'kitchen-sink-livewire-latest-pages');

    throw_unless(is_array($pagesCardWidget), RuntimeException::class, 'Expected pages-card widget data.');

    $widget = Widget::query()
        ->with(['assets', 'translations', 'type'])
        ->firstWhere('key', $pagesCardWidget['widget_key']);

    expect($widget)->toBeInstanceOf(Widget::class);
    assert($widget instanceof Widget);

    expect($widget->meta['pagination'] ?? null)->toBeTrue()
        ->and($widget->assets)->toHaveCount(kitchenSinkExpectedContextAssetLimit())
        ->and($bladeHtml)->not->toContain('data-capell-authoring')
        ->and($bladeHtml)->not->toContain('data-field-path');
});

function kitchenSinkLayoutHtml(Page $page): string
{
    kitchenSinkBindFrontendContext($page);

    $page->loadMissing(['layout', 'site.theme', 'translations.language']);
    $layout = kitchenSinkRequiredLayout($page->layout);
    $container = kitchenSinkMainContainer($layout);
    $translationContent = $page->translations->first()?->getAttribute('content');
    $html = is_string($translationContent) ? $translationContent : '';

    foreach ($container['widgets'] as $widgetIndex => $widgetData) {
        $widget = Widget::query()
            ->with(['assets.asset', 'assets.media', 'translations', 'type'])
            ->firstWhere('key', $widgetData['widget_key']);

        if (! $widget instanceof Widget) {
            continue;
        }

        $widget->assets->each(function (WidgetAsset $widgetAsset): void {
            if ($widgetAsset->asset instanceof Page) {
                $widgetAsset->asset->loadMissing(['children', 'image', 'media', 'pageUrl', 'parent.translation', 'translation', 'type']);
            }
        });

        $widgetHtml = view('capell-layout-builder::components.layout.widget', [
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

        $html .= $widgetHtml;
    }

    return $html;
}

function kitchenSinkBindFrontendContext(Page $page): void
{
    $page->loadMissing(['layout', 'site.theme', 'translations.language']);
    $language = $page->translations->first()?->language;
    $site = $page->site;
    $layout = $page->layout;
    $theme = $site?->theme;
    $page->loadMissing(['pageUrl.siteDomain']);

    throw_if(! $site instanceof Site || ! $layout instanceof Layout || ! $language instanceof Language || ! $theme instanceof Theme, RuntimeException::class, 'Expected kitchen sink page context to be loaded.');

    $pageUrl = $page->pageUrl;
    $siteDomain = $pageUrl->siteDomain ?? $site->siteDomains()->first();

    if ($pageUrl !== null) {
        $request = Request::create($pageUrl->full_url, SymfonyRequest::METHOD_GET);
        $route = new Route(['GET', 'HEAD'], ltrim($pageUrl->url, '/') ?: '/', []);
        $route->bind($request);
        $request->setRouteResolver(fn (): Route => $route);

        app()->instance('request', $request);

        $currentRoute = new ReflectionProperty(resolve(Router::class)::class, 'current');
        $currentRoute->setValue(resolve(Router::class), $route);
    }

    resolve(FrontendState::class)
        ->withSite($site)
        ->withLanguage($language)
        ->withPage($page)
        ->withLayout($layout)
        ->withTheme($theme);

    if ($siteDomain !== null) {
        resolve(FrontendState::class)->withDomain($siteDomain);
    }

    if ($page->pageUrl !== null) {
        resolve(FrontendState::class)
            ->withRelativePath($page->pageUrl->url)
            ->setEffectiveUrl($page->pageUrl->url);
    }

    Frontend::clearResolvedInstance(CapellFrontendContext::class);
    app()->instance(
        CapellFrontendContext::class,
        new CapellFrontendContext(
            (new FrontendState)
                ->withSite($site)
                ->withLanguage($language)
                ->withPage($page)
                ->withLayout($layout)
                ->withTheme($theme),
        ),
    );
}

/**
 * @return array<int, string>
 */
function kitchenSinkHeadingsFromHtml(string $html): array
{
    preg_match_all('/<h2[^>]*>(.*?)<\/h2>/s', $html, $matches);

    return collect($matches[1])->map(fn (string $heading): string => trim(strip_tags($heading)))->values()->all();
}

/**
 * @return array<int, string>
 */
function kitchenSinkReferenceHeadingsFromHtml(string $html): array
{
    preg_match_all('/<section class="capell-kitchen-sink-reference">(.*?)<\/section>/s', $html, $sections);

    $referenceBlockHeadings = collect($sections[1])
        ->flatMap(fn (string $sectionHtml): array => kitchenSinkHeadingsFromHtml($sectionHtml))
        ->values()
        ->all();

    $allHeadings = [
        ...kitchenSinkHeadingsFromHtml($html),
        ...$referenceBlockHeadings,
    ];

    return collect(InstallKitchenSinkDemoPageAction::sectionHeadings())
        ->filter(fn (string $heading): bool => in_array($heading, $allHeadings, true))
        ->values()
        ->all();
}
