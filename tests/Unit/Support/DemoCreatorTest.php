<?php

declare(strict_types=1);

use Capell\ContentSections\Models\Section;
use Capell\Core\Enums\ContainerWidthEnum;
use Capell\Core\Enums\ContentStructure;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\DemoKit\Filament\Configurators\Widgets\HomepageSectionWidgetConfigurator;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\DemoKit\Support\Creator\DemoResourceResolver;
use Capell\DemoKit\Support\DemoPageContentAssetSections;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Filament\Resources\Widgets\Pages\CreateWidget;
use Capell\LayoutBuilder\Filament\Resources\Widgets\Pages\EditWidget;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Capell\LayoutBuilder\Support\Loader\LayoutLoader;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    foreach (CapellLayoutBuilderManager::getMigrations() as $migration) {
        $instance = include dirname(__DIR__, 4) . '/layout-builder/database/migrations/' . $migration . '.php';

        $instance->up();
    }

    LayoutBuilderInstallPackageAction::run();
});

function demoCreatorRequiredLayout(Page $page): Layout
{
    $layout = $page->layout;

    throw_unless($layout instanceof Layout, RuntimeException::class, 'Expected the demo page to have a layout.');

    return $layout;
}

function demoCreatorRequiredWidget(?Widget $widget): Widget
{
    throw_unless($widget instanceof Widget, RuntimeException::class, 'Expected a demo widget.');

    return $widget;
}

function demoCreatorRequiredWidgetAsset(?WidgetAsset $asset): WidgetAsset
{
    throw_unless($asset instanceof WidgetAsset, RuntimeException::class, 'Expected a demo widget asset.');

    return $asset;
}

/**
 * @return array<string, mixed>
 */
function demoCreatorWidgetAssetMeta(WidgetAsset $asset): array
{
    return is_array($asset->meta) ? $asset->meta : [];
}

function useTinyDemoKitWidgetResources(): void
{
    $demoDirectory = sys_get_temp_dir() . '/capell-demo-kit-widget-resources-' . uniqid();
    $imageDirectory = $demoDirectory . '/img';
    $videoDirectory = $demoDirectory . '/video';

    File::ensureDirectoryExists($imageDirectory);
    File::ensureDirectoryExists($videoDirectory);

    $image = imagecreatetruecolor(32, 32);
    assert($image instanceof GdImage);

    $background = imagecolorallocate($image, 34, 139, 230);
    assert(is_int($background));

    imagefilledrectangle($image, 0, 0, 31, 31, $background);

    foreach (['fallback', 'home', 'pricing', 'sharks'] as $imageName) {
        imagejpeg($image, $imageDirectory . '/' . $imageName . '.jpg', 85);
    }

    imagedestroy($image);

    File::put($videoDirectory . '/SampleVideo_1280x720_1mb.mp4', 'video');

    test()->beforeApplicationDestroyed(function () use ($demoDirectory): void {
        File::deleteDirectory($demoDirectory);
    });

    app()->instance(DemoResourceResolver::class, new readonly class($demoDirectory)
    {
        public function __construct(private string $demoDirectory) {}

        public function resolve(?string $folder): string
        {
            return $this->demoDirectory . ($folder === null || $folder === '' ? '' : '/' . ltrim($folder, '/'));
        }
    });
}

/**
 * @return array{creator: DemoCreator, languages: Collection<int, Language>, site: Site, page: Page}
 */
function prepareDemoKitWidgetCreatorFixture(): array
{
    useTinyDemoKitWidgetResources();
    installContentSectionsForDemoAssets();
    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');
    config()->set('media-library.conversions_disk', 'public');

    $typeCreator = resolve(TypeCreator::class);
    $typeCreator->createDefaultContentType();
    $typeCreator->createBuilderContentType();
    $typeCreator->createWidgetTypes();

    $language = Language::factory()->english()->create();
    $languages = Language::query()->whereKey($language->getKey())->get();
    $site = Site::factory()->language($language)->default()->withTranslations($languages)->create();
    $pageType = Blueprint::factory()->page()->default()->create([
        'name' => 'Default Page',
        'key' => 'default',
        'status' => true,
        'meta' => [
            'accessible' => true,
            'listable' => true,
        ],
    ]);

    $creator = resolve(DemoCreator::class);
    $creator->setupSite($site, $languages);

    $page = Page::factory()
        ->site($site)
        ->withTranslations($languages)
        ->create([
            'name' => 'Manual Widget Fixture Page',
            'blueprint_id' => $pageType->getKey(),
        ]);

    $creator->createMedia($page, 'home', collection: MediaCollectionEnum::Image);

    Page::factory()
        ->count(4)
        ->site($site)
        ->withTranslations($languages)
        ->create(['blueprint_id' => $pageType->getKey()])
        ->each(function (Page $relatedPage) use ($creator): void {
            $creator->createMedia($relatedPage, 'fallback', collection: MediaCollectionEnum::Image);
        });

    return [
        'creator' => $creator,
        'languages' => $languages,
        'site' => $site,
        'page' => $page,
    ];
}

/**
 * @param  array{creator: DemoCreator, languages: Collection<int, Language>, site: Site, page: Page}  $fixture
 * @return array<string, callable(): Widget>
 */
function demoKitWidgetCreatorCases(array $fixture): array
{
    $creator = $fixture['creator'];
    $languages = $fixture['languages'];
    $site = $fixture['site'];
    $page = $fixture['page'];

    return [
        'createApHeroBannerWidget' => $creator->createApHeroBannerWidget(...),
        'createApCardGridWidget' => $creator->createApCardGridWidget(...),
        'createApFeatureListWidget' => $creator->createApFeatureListWidget(...),
        'createFeatureListWidget' => $creator->createFeatureListWidget(...),
        'createApCtaSectionWidget' => $creator->createApCtaSectionWidget(...),
        'createApImageGalleryWidget' => $creator->createApImageGalleryWidget(...),
        'createModernFeatureListWidget' => $creator->createModernFeatureListWidget(...),
        'createModernTeamMembersWidget' => $creator->createModernTeamMembersWidget(...),
        'createModernPricingTableWidget' => $creator->createModernPricingTableWidget(...),
        'createModernTestimonialsWidget' => $creator->createModernTestimonialsWidget(...),
        'createModernFaqWidget' => $creator->createModernFaqWidget(...),
        'createModernStatsSectionWidget' => $creator->createModernStatsSectionWidget(...),
        'createModernAlternatingContentWidget' => $creator->createModernAlternatingContentWidget(...),
        'createModernProcessStepsWidget' => $creator->createModernProcessStepsWidget(...),
        'createModernImageGalleryWidget' => $creator->createModernImageGalleryWidget(...),
        'createContentWidget' => fn (): Widget => $creator->createContentWidget($languages),
        'createSplitContentWidget' => fn (): Widget => $creator->createSplitContentWidget($languages),
        'createBannerImageWidget' => fn (): Widget => $creator->createBannerImageWidget($languages),
        'createGalleryWidget' => $creator->createGalleryWidget(...),
        'createPageCardsWidget' => fn (): Widget => $creator->createPageCardsWidget($page),
        'createFaqWidget' => fn (): Widget => $creator->createFaqWidget($languages),
        'createMediaCarouselWidget' => $creator->createMediaCarouselWidget(...),
        'createStaticNavigationWidget' => fn (): Widget => $creator->createStaticNavigationWidget($languages, $site),
        'createClientLogosWidget' => fn (): Widget => $creator->createClientLogosWidget($languages),
        'createBusinessFeaturesWidget' => fn (): Widget => $creator->createBusinessFeaturesWidget($site),
        'createBannersWidget' => $creator->createBannersWidget(...),
        'createTestimonialsWidget' => fn (): Widget => $creator->createTestimonialsWidget($languages),
        'createStatisticsWidget' => $creator->createStatisticsWidget(...),
        'createTeamPortfolioWidget' => fn (): Widget => $creator->createTeamPortfolioWidget($languages),
        'createHomepageHeroCommandCenterWidget' => $creator->createHomepageHeroCommandCenterWidget(...),
        'createHomepageProofStripWidget' => $creator->createHomepageProofStripWidget(...),
        'createHomepageDemoShowcaseWidget' => $creator->createHomepageDemoShowcaseWidget(...),
        'createHomepageDemoWidgetsCarouselWidget' => $creator->createHomepageDemoWidgetsCarouselWidget(...),
        'createHomepageMarketplaceWidget' => $creator->createHomepageMarketplaceWidget(...),
        'createHomepageTechnicalPipelineWidget' => $creator->createHomepageTechnicalPipelineWidget(...),
        'createHomepageRouteSplitWidget' => $creator->createHomepageRouteSplitWidget(...),
        'createHomepageFinalCtaWidget' => $creator->createHomepageFinalCtaWidget(...),
    ];
}

/**
 * @return list<string>
 */
function publicDemoKitWidgetCreatorMethodNames(): array
{
    return array_values(collect(new ReflectionClass(DemoCreator::class)->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(function (ReflectionMethod $method): bool {
            if (! str_starts_with($method->getName(), 'create')) {
                return false;
            }

            if (! str_ends_with($method->getName(), 'Widget')) {
                return false;
            }

            $returnType = $method->getReturnType();

            return $returnType instanceof ReflectionNamedType && $returnType->getName() === Widget::class;
        })
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->sort()
        ->values()
        ->all());
}

/**
 * @param  array<string, mixed>  $actual
 * @param  array<string, mixed>  $expected
 */
function expectDemoCreatorArrayValuesPreserved(array $actual, array $expected, string $context): void
{
    foreach ($expected as $key => $expectedValue) {
        expect($actual)->toHaveKey($key);

        if (is_array($expectedValue)) {
            $actualValue = $actual[$key] ?? null;

            expect($actualValue)->toBeArray($context . '.' . $key);

            expectDemoCreatorArrayValuesPreserved($actualValue, $expectedValue, $context . '.' . $key);

            continue;
        }

        expect($actual[$key] ?? null)->toBe($expectedValue, $context . '.' . $key);
    }
}

/**
 * @return array<string, mixed>
 */
function manualWidgetFormStateFromDemoWidget(Widget $widget, string $key): array
{
    $meta = [];

    if (is_array($widget->meta) && array_key_exists('navigation', $widget->meta)) {
        $meta['navigation'] = $widget->meta['navigation'];
    }

    $translations = $widget->translations()
        ->get()
        ->mapWithKeys(fn (Translation $translation): array => [
            (string) Str::uuid() => [
                'language_id' => $translation->language_id,
                'title' => $translation->title,
                'content' => '<p>Manual demo widget content.</p>',
                'summary' => $translation->summary,
                'meta' => $translation->meta,
            ],
        ])
        ->all();

    return [
        'name' => $widget->name . ' Manual',
        'key' => $key,
        'blueprint_id' => $widget->blueprint_id,
        'status' => (bool) $widget->status,
        'visible_from' => $widget->visible_from,
        'visible_until' => $widget->visible_until,
        'component' => $widget->component,
        'component_item' => $widget->component_item,
        'is_livewire' => $widget->is_livewire,
        'view_file' => $widget->view_file,
        'meta' => $meta,
        'translations' => $translations,
    ];
}

function installContentSectionsForDemoAssets(): void
{
    if (! Schema::hasTable('sections')) {
        $migration = include dirname(__DIR__, 4) . '/content-sections/database/migrations/2026_05_10_190844_01_create_sections_table.php';

        $migration->up();
    }
}

function createDemoAssetPage(string $name): Page
{
    installContentSectionsForDemoAssets();
    resolve(TypeCreator::class)->createWidgetTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();
    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => $name],
        'title' => ['en' => $name],
    ], $site, createMedia: false);

    throw_unless($page instanceof Page);

    return $page->refresh()->loadMissing(['layout', 'translation', 'blueprint']);
}

it('creates homepage demo snippets as layout builder widgets', function (): void {
    resolve(TypeCreator::class)->createWidgetTypes();

    $widget = resolve(DemoCreator::class)->createHomepageHeroCommandCenterWidget();

    expect($widget)->toBeInstanceOf(Widget::class)
        ->and($widget->getTable())->toBe('widgets')
        ->and($widget->key)->toBe('capell-home-hero-command-center')
        ->and($widget->component)->toBe(DemoKitServiceProvider::HomepageSectionRenderable)
        ->and($widget->getMeta('container'))->toBe(ContainerWidthEnum::Default->value)
        ->and($widget->getMeta('margin'))->toBe(['none'])
        ->and($widget->getMeta('padding'))->toBe(['none'])
        ->and($widget->getViewFile())->toBeNull();
});

it('persists every public demo kit widget creator output', function (): void {
    $fixture = prepareDemoKitWidgetCreatorFixture();
    $cases = demoKitWidgetCreatorCases($fixture);

    expect(collect(array_keys($cases))->sort()->values()->all())
        ->toBe(publicDemoKitWidgetCreatorMethodNames());

    foreach ($cases as $method => $createWidget) {
        $widget = $createWidget()->refresh()->loadMissing(['translations', 'assets', 'media', 'blueprint']);
        $persistedWidget = Widget::query()
            ->with(['translations', 'assets', 'media'])
            ->whereKey($widget->getKey())
            ->firstOrFail();

        expect($persistedWidget->exists)->toBeTrue($method)
            ->and($persistedWidget->name)->toBe($widget->name, $method)
            ->and($persistedWidget->key)->toBe($widget->key, $method)
            ->and($persistedWidget->blueprint_id)->toBe($widget->blueprint_id, $method)
            ->and($persistedWidget->component)->toBe($widget->component, $method)
            ->and($persistedWidget->component_item)->toBe($widget->component_item, $method)
            ->and($persistedWidget->view_file)->toBe($widget->view_file, $method)
            ->and($persistedWidget->is_livewire)->toBe($widget->is_livewire, $method)
            ->and($persistedWidget->meta)->toBe($widget->meta, $method)
            ->and($persistedWidget->admin)->toBe($widget->admin, $method)
            ->and($persistedWidget->translations)->toHaveCount($widget->translations->count(), $method)
            ->and($persistedWidget->assets)->toHaveCount($widget->assets->count(), $method)
            ->and($persistedWidget->media)->toHaveCount($widget->media->count(), $method);

        assertDatabaseHas('widgets', [
            'id' => $widget->getKey(),
            'key' => $widget->key,
            'blueprint_id' => $widget->blueprint_id,
        ]);
    }
});

it('can manually create every demo kit widget type through Filament', function (): void {
    test()->actingAsAdmin();

    $fixture = prepareDemoKitWidgetCreatorFixture();

    foreach (demoKitWidgetCreatorCases($fixture) as $method => $createWidget) {
        $demoWidget = $createWidget()->refresh();
        $manualKey = $demoWidget->key . '-manual-' . Str::random(8);
        $state = manualWidgetFormStateFromDemoWidget($demoWidget, $manualKey);

        Livewire::test(CreateWidget::class)
            ->assertSuccessful()
            ->set('data.blueprint_id', $demoWidget->blueprint_id)
            ->set('data.translations', [])
            ->fillForm($state)
            ->call('create')
            ->assertHasNoFormErrors();

        $manualWidget = Widget::query()
            ->where('key', $manualKey)
            ->with('translations')
            ->firstOrFail();

        expect($manualWidget->name)->toBe($state['name'], $method)
            ->and($manualWidget->blueprint_id)->toBe($demoWidget->blueprint_id, $method)
            ->and($manualWidget->component)->toBe($demoWidget->component, $method)
            ->and($manualWidget->component_item)->toBe($demoWidget->component_item, $method)
            ->and($manualWidget->view_file)->toBe($demoWidget->view_file, $method)
            ->and((bool) $manualWidget->is_livewire)->toBe((bool) $demoWidget->is_livewire, $method)
            ->and($manualWidget->meta)->toBeArray($method)
            ->and($manualWidget->translations)->toHaveCount($demoWidget->translations()->count(), $method);
    }
});

it('can edit every demo kit creator widget through Filament without losing creator data', function (): void {
    test()->actingAsAdmin();

    $fixture = prepareDemoKitWidgetCreatorFixture();
    $language = $fixture['languages']->first();

    expect($language)->toBeInstanceOf(Language::class);
    assert($language instanceof Language);

    foreach (demoKitWidgetCreatorCases($fixture) as $method => $createWidget) {
        $widget = $createWidget()->refresh()->loadMissing(['translations', 'assets', 'media', 'blueprint']);
        $originalMeta = $widget->meta;
        $originalAdmin = $widget->admin;
        $originalAssetIds = $widget->assets->pluck('id')->sort()->values()->all();
        $originalMediaIds = $widget->media->pluck('id')->sort()->values()->all();
        $existingTranslation = $widget->translations->firstWhere('language_id', $language->getKey());
        $editedName = $widget->name . ' Edited';
        $editedTitle = $widget->name . ' edited title';
        $editedHtml = '<p>Edited demo creator widget content for ' . e($widget->key) . '.</p>';
        $editedContent = $widget->blueprint?->content_structure === ContentStructure::Blocks
            ? [[
                'type' => 'content',
                'data' => [
                    'content' => $editedHtml,
                    'mediaAlign' => null,
                    'mediaOrdering' => null,
                ],
            ]]
            : $editedHtml;

        try {
            $component = Livewire::test(EditWidget::class, ['record' => $widget->getRouteKey()])
                ->assertSuccessful()
                ->set('data.name', $editedName)
                ->set('data.status', (bool) $widget->status);

            if ($existingTranslation instanceof Translation) {
                $component
                    ->set('data.translations.record-' . $existingTranslation->getKey() . '.title', $editedTitle)
                    ->set('data.translations.record-' . $existingTranslation->getKey() . '.content', $editedContent);
            }

            $component
                ->call('save')
                ->assertHasNoFormErrors();
        } catch (Throwable $throwable) {
            throw new RuntimeException($method . ': ' . $throwable->getMessage(), $throwable->getCode(), previous: $throwable);
        }

        $editedWidget = $widget->refresh()->loadMissing(['assets', 'media']);
        $editedTranslation = $editedWidget->translations()
            ->where('language_id', $language->getKey())
            ->first();

        expect($editedWidget->name)->toBe($editedName, $method)
            ->and($editedWidget->assets->pluck('id')->sort()->values()->all())->toBe($originalAssetIds, $method)
            ->and($editedWidget->media->pluck('id')->sort()->values()->all())->toBe($originalMediaIds, $method);

        if ($existingTranslation instanceof Translation) {
            expect($editedTranslation?->title)->toBe($editedTitle, $method);

            if ($widget->blueprint?->content_structure === ContentStructure::Blocks) {
                $persistedContent = json_decode((string) $editedTranslation?->getRawOriginal('content'), true);
                $persistedContent = demoCreatorWithoutInstanceMetadata($persistedContent);

                expect($persistedContent)->toBe($editedContent, $method);
            } else {
                expect($editedTranslation?->content)->toBe($editedContent, $method);
            }
        }

        if (is_array($originalAdmin) && $originalAdmin !== []) {
            expect($editedWidget->admin)->toMatchArray($originalAdmin, $method . ':admin');
        }

        if (is_array($originalMeta) && $originalMeta !== []) {
            expectDemoCreatorArrayValuesPreserved($editedWidget->meta ?? [], $originalMeta, $method . ':meta');
        }
    }
});

function demoCreatorWithoutInstanceMetadata(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    unset($value['__capell']);

    return array_map(demoCreatorWithoutInstanceMetadata(...), $value);
}

it('creates the interactive homepage widgets carousel widget', function (): void {
    resolve(TypeCreator::class)->createWidgetTypes();

    $widget = resolve(DemoCreator::class)->createHomepageDemoWidgetsCarouselWidget();
    $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/components/widget/homepage-section.blade.php');

    expect($widget)->toBeInstanceOf(Widget::class)
        ->and($widget->key)->toBe('capell-home-demo-widgets-carousel')
        ->and($widget->component)->toBe(DemoKitServiceProvider::HomepageSectionRenderable)
        ->and($widget->blueprint?->key)->toBe('homepage-section')
        ->and($widget->blueprint?->admin)->toMatchArray([
            'configurator' => HomepageSectionWidgetConfigurator::getKey(),
        ])
        ->and($widget->meta)->toHaveKey('content')
        ->and(data_get($widget->meta, 'content.items.0.title'))->toBe('Editorial workflow')
        ->and($view)->toContain('window.innerWidth >= 1024 ? 4 : window.innerWidth >= 768 ? 2 : 1')
        ->and($view)->toContain('pageCount()')
        ->and($view)->toContain('x-on:touchstart.passive="swipeStart($event)"')
        ->and($view)->toContain('x-on:touchend.passive="swipeEnd($event)"')
        ->and($view)->toContain('$homepageItems(\'items\')')
        ->and($view)->not->toContain('Editorial workflow');
});

it('uses a blade-backed demo page content widget for designed demo pages', function (): void {
    resolve(TypeCreator::class)->createWidgetTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => 'About Us'],
        'title' => ['en' => 'About Us'],
    ], $site, createMedia: false);

    $page->refresh();

    expect($page->layout?->widgets)->toBe(['demo-page-hero', 'breadcrumbs', 'demo-page-content'])
        ->and(Widget::query()->where('key', 'demo-page-content')->value('component'))->toBe('capell.widget.demo-page-content')
        ->and(Widget::query()->where('key', 'demo-page-content')->value('view_file'))->toBeNull()
        ->and(Widget::query()->where('key', 'demo-page-hero')->value('component'))->toBe('capell.widget.hero')
        ->and($page->translation?->content)->toContain('<p>Capell combines Laravel package discipline')
        ->and($page->translation?->content)->not->toContain('class=');
});

it('canonicalizes the old architecture demo page into the platform architecture layout', function (): void {
    resolve(TypeCreator::class)->createWidgetTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => 'Home, Buildings and Architecture'],
        'title' => ['en' => 'Home, Buildings and Architecture'],
    ], $site, createMedia: false);

    $page->refresh()->loadMissing(['layout', 'translation']);

    expect($page->name)->toBe('Platform Architecture')
        ->and($page->layout?->key)->toBe('capell-demo-platform-architecture')
        ->and($page->layout?->widgets)->toContain('demo-page-hero')
        ->and($page->layout?->widgets)->toContain('demo-page-content')
        ->and($page->translation?->title)->toBe('Platform Architecture')
        ->and($page->translation?->getMeta('label'))->toBe('Platform Architecture')
        ->and($page->translation?->getMeta('slug'))->toBe('platform-architecture')
        ->and($page->translation?->getMeta('hero_title'))->toBe('Platform Architecture')
        ->and($page->translation?->getMeta('hero'))->toBeString()
        ->and($page->translation?->getMeta('hero'))->toStartWith('<p>')
        ->and(Widget::query()->where('key', 'demo-page-hero')->value('component'))->toBe('capell.widget.hero');
});

it('keeps support pages without heroes on content-only layouts', function (): void {
    resolve(TypeCreator::class)->createWidgetTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => 'FAQ'],
        'title' => ['en' => 'FAQ'],
    ], $site, createMedia: false);

    $page->refresh()->loadMissing(['layout', 'translation']);

    expect($page->layout?->key)->toBe('capell-demo-faq-no-hero')
        ->and($page->layout?->widgets)->not->toContain('hero')
        ->and($page->layout?->widgets)->toContain('demo-page-content')
        ->and($page->translation?->getMeta('hero'))->toBeNull()
        ->and($page->translation?->getMeta('hero_title'))->toBe('Faq');
});

it('uses a custom hero image layout for pricing', function (): void {
    resolve(TypeCreator::class)->createWidgetTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => 'Pricing'],
        'title' => ['en' => 'Pricing'],
    ], $site);

    $page->refresh()->loadMissing(['layout', 'media', 'translation']);

    if (! $page instanceof Page) {
        throw new RuntimeException('Expected DemoCreator to return a page.');
    }

    expect($page->layout?->key)->toBe('capell-demo-pricing')
        ->and($page->layout?->widgets)->toContain('demo-page-hero')
        ->and($page->meta)->toMatchArray(['show_hero' => true, 'hero_style' => 'compact'])
        ->and($page->translation?->getMeta('hero'))->toBeString();

    $pageMedia = $page->getRelation('media');
    expect($pageMedia instanceof Collection ? $pageMedia->count() : 0)->toBe(1);
    expect($pageMedia instanceof Collection ? $pageMedia->first()?->getAttribute('file_name') : null)->toBe('pricing.jpg');
});

it('seeds distinct page scoped assets for reusable demo page content widget', function (): void {
    installContentSectionsForDemoAssets();
    resolve(TypeCreator::class)->createWidgetTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();
    $pages = [
        'Services' => 'services-workbench',
        'Pricing' => 'pricing-matrix',
        'Resources' => 'resources-library',
        'FAQ' => 'faq-support',
        'Contact' => 'contact-routing',
    ];

    foreach ($pages as $name => $variant) {
        $page = resolve(DemoCreator::class)->createPage([
            'name' => ['en' => $name],
            'title' => ['en' => $name],
        ], $site, createMedia: false);

        throw_unless($page instanceof Page);

        $widget = Widget::query()->where('key', 'demo-page-content')->firstOrFail();
        $assets = WidgetAsset::query()
            ->where('widget_id', $widget->getKey())
            ->where('pageable_type', $page->getMorphClass())
            ->where('pageable_id', $page->getKey())
            ->where('container', 'main')
            ->where('occurrence', 1)
            ->get();
        $asset = demoCreatorRequiredWidgetAsset($assets->first());
        $assetMeta = demoCreatorWidgetAssetMeta($asset);

        expect($assets)->toHaveCount(1)
            ->and($assetMeta['variant'] ?? null)->toBe($variant)
            ->and($asset->asset)->toBeInstanceOf(Section::class);
    }
});

it('seeds uniform contact routing content for designed contact pages', function (): void {
    $page = createDemoAssetPage('Contact');
    $widget = Widget::query()->where('key', 'demo-page-content')->firstOrFail();

    $asset = WidgetAsset::query()
        ->where('widget_id', $widget->getKey())
        ->where('pageable_type', $page->getMorphClass())
        ->where('pageable_id', $page->getKey())
        ->where('container', 'main')
        ->where('occurrence', 1)
        ->firstOrFail();
    $assetMeta = demoCreatorWidgetAssetMeta($asset);

    expect($assetMeta['variant'] ?? null)->toBe('contact-routing')
        ->and($asset->asset)->toBeInstanceOf(Section::class)
        ->and($assetMeta['title'] ?? null)->toBe('Start the right conversation')
        ->and($assetMeta['items'] ?? [])->toContain(['label' => 'Project scoping', 'title' => 'New implementations', 'copy' => 'Plan content models, package boundaries, layouts, and launch checks before the build starts.'])
        ->and($assetMeta['items'] ?? [])->toContain(['label' => 'Migration planning', 'title' => 'Move from legacy CMSs', 'copy' => 'Map pages, redirects, media, structured fields, and verification work into a clear migration path.'])
        ->and($assetMeta['items'] ?? [])->toContain(['label' => 'Partnerships', 'title' => 'Agency and technology work', 'copy' => 'Discuss delivery partnerships, packaged integrations, and repeatable theme or content operations.']);
});

it('keeps seeded demo page assets idempotent and preserves editor assets', function (): void {
    $page = createDemoAssetPage('Services');
    $widget = Widget::query()->where('key', 'demo-page-content')->firstOrFail();
    $sectionType = Blueprint::query()->firstOrCreate([
        'type' => 'section',
        'key' => 'demo-page-content-editor-asset',
    ], [
        'name' => 'Demo Page Content Editor Asset',
        'group' => 'demo',
        'status' => true,
    ]);
    $editorAsset = Section::query()->create([
        'name' => 'Editor-added service asset',
        'blueprint_id' => $sectionType->getKey(),
        'site_id' => $page->site_id,
        'visible_from' => now()->subDay(),
    ]);

    $widget->assets()->create([
        'pageable_id' => $page->getKey(),
        'pageable_type' => $page->getMorphClass(),
        'container' => 'main',
        'occurrence' => 1,
        'asset_type' => $editorAsset->getMorphClass(),
        'asset_id' => $editorAsset->getKey(),
        'order' => 99,
        'meta' => ['editor_added' => true],
    ]);

    $creator = resolve(DemoCreator::class);
    $method = new ReflectionMethod($creator, 'syncDemoPageContentAssets');
    $method->invoke($creator, $page, 'Services');

    $pageAssets = WidgetAsset::query()
        ->where('widget_id', $widget->getKey())
        ->where('pageable_type', $page->getMorphClass())
        ->where('pageable_id', $page->getKey())
        ->where('container', 'main')
        ->where('occurrence', 1)
        ->get();
    $editorPageAsset = demoCreatorRequiredWidgetAsset($pageAssets->where('asset_id', $editorAsset->getKey())->first());
    $editorAssetMeta = demoCreatorWidgetAssetMeta($editorPageAsset);

    expect($pageAssets)->toHaveCount(2)
        ->and($pageAssets->where('meta.demo_kit_seed', true))->toHaveCount(1)
        ->and($editorAssetMeta['editor_added'] ?? null)->toBeTrue();
});

it('preloads asset backed demo page content without lazy loading widget assets', function (): void {
    $page = createDemoAssetPage('Services');
    $language = Language::query()->where('default', true)->firstOrFail();
    $loadedWidget = demoCreatorRequiredWidget(resolve(LayoutLoader::class)->getLayoutWidget(
        demoCreatorRequiredLayout($page),
        'demo-page-content',
        $language,
        $page,
        'main',
        1,
    ));
    $firstAsset = demoCreatorRequiredWidgetAsset($loadedWidget->assets->first());
    $assetMeta = demoCreatorWidgetAssetMeta($firstAsset);

    expect($loadedWidget)->toBeInstanceOf(Widget::class)
        ->and($loadedWidget->relationLoaded('assets'))->toBeTrue()
        ->and($loadedWidget->assets)->toHaveCount(1)
        ->and($assetMeta['variant'] ?? null)->toBe('services-workbench');
});

it('renders asset backed demo page content without exposing layout metadata', function (): void {
    $page = createDemoAssetPage('Services');
    $language = Language::query()->where('default', true)->firstOrFail();
    $loadedWidget = demoCreatorRequiredWidget(resolve(LayoutLoader::class)->getLayoutWidget(
        demoCreatorRequiredLayout($page),
        'demo-page-content',
        $language,
        $page,
        'main',
        1,
    ));
    $sections = resolve(DemoPageContentAssetSections::class)
        ->resolve($loadedWidget, $page, 'main', 1);
    $classes = [
        'sectionClass' => 'grid gap-6 border-b border-slate-200/70 py-10',
        'splitSectionClass' => 'grid gap-6 border-b border-slate-200/70 py-10 lg:grid-cols-2',
        'carouselClass' => 'grid gap-4 md:grid-cols-3',
        'compactCarouselClass' => 'grid gap-4 md:grid-cols-2',
        'carouselItemClass' => 'min-w-0',
        'cardClass' => 'grid gap-3 rounded-lg border border-slate-200 bg-white p-5',
        'eyebrowClass' => 'text-xs font-extrabold uppercase text-[#0f766e]',
        'headingClass' => 'text-3xl font-extrabold text-[#131b2e]',
        'introClass' => 'text-base leading-8 text-slate-600',
        'labelClass' => 'text-xs font-extrabold uppercase text-[#0f766e]',
        'cardTitleClass' => 'text-xl font-extrabold text-slate-950',
        'cardCopyClass' => 'text-base leading-7 text-slate-600',
    ];

    $html = view('capell-demo-kit::components.widget.demo-page-content-assets', [
        'sections' => $sections,
        ...$classes,
    ])->render();

    expect($html)->toContain('service-delivery-lanes')
        ->and($html)->toContain('Implementation services for complex Capell rollouts')
        ->and($html)->not->toContain('widget_asset')
        ->and($html)->not->toContain('widget_assets')
        ->and($html)->not->toContain('pageable_id')
        ->and($html)->not->toContain('pageable_type')
        ->and($html)->not->toContain('asset_id')
        ->and($html)->not->toContain('asset_type')
        ->and($html)->not->toContain('workspace_id')
        ->and($html)->not->toContain('Capell\\');
});

it('falls back when demo page assets are not preloaded', function (): void {
    $page = createDemoAssetPage('Services');
    $widget = Widget::query()->where('key', 'demo-page-content')->firstOrFail();

    $sections = resolve(DemoPageContentAssetSections::class)
        ->resolve($widget, $page, 'main', 1);

    expect($widget->relationLoaded('assets'))->toBeFalse()
        ->and($sections)->toBe([]);
});
