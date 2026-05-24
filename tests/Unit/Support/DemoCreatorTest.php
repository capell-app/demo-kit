<?php

declare(strict_types=1);

use Capell\ContentSections\Models\Section;
use Capell\Core\Enums\ContainerWidthEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\DemoKit\Support\DemoPageContentAssetSections;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Models\Block;
use Capell\LayoutBuilder\Models\BlockAsset;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Capell\LayoutBuilder\Support\Loader\LayoutLoader;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    foreach (CapellLayoutBuilderManager::getMigrations() as $migration) {
        $instance = include dirname(__DIR__, 4) . '/layout-builder/database/migrations/' . $migration . '.php';

        $instance->up();
    }

    LayoutBuilderInstallPackageAction::run();
});

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
    resolve(TypeCreator::class)->createBlockTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();
    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => $name],
        'title' => ['en' => $name],
    ], $site, createMedia: false);

    throw_unless($page instanceof Page);

    return $page->refresh()->loadMissing(['layout', 'translation', 'type']);
}

it('creates homepage demo snippets as layout builder blocks', function (): void {
    resolve(TypeCreator::class)->createBlockTypes();

    $block = resolve(DemoCreator::class)->createHomepageHeroCommandCenterBlock();

    expect($block)->toBeInstanceOf(Block::class)
        ->and($block->getTable())->toBe('blocks')
        ->and($block->key)->toBe('capell-home-hero-command-center')
        ->and($block->component)->toBe(DemoKitServiceProvider::HomepageSectionRenderable)
        ->and($block->getMeta('container'))->toBe(ContainerWidthEnum::Default->value)
        ->and($block->getMeta('margin'))->toBe(['none'])
        ->and($block->getMeta('padding'))->toBe(['none'])
        ->and($block->getViewFile())->toBeNull();
});

it('creates the interactive homepage widgets carousel block', function (): void {
    resolve(TypeCreator::class)->createBlockTypes();

    $block = resolve(DemoCreator::class)->createHomepageDemoWidgetsCarouselBlock();
    $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/components/block/homepage-section.blade.php');

    expect($block)->toBeInstanceOf(Block::class)
        ->and($block->key)->toBe('capell-home-demo-widgets-carousel')
        ->and($block->component)->toBe(DemoKitServiceProvider::HomepageSectionRenderable)
        ->and($view)->toContain('window.innerWidth >= 1024 ? 4 : window.innerWidth >= 768 ? 2 : 1')
        ->and($view)->toContain('pageCount()')
        ->and($view)->toContain('x-on:touchstart.passive="swipeStart($event)"')
        ->and($view)->toContain('x-on:touchend.passive="swipeEnd($event)"')
        ->and($view)->toContain('Editorial workflow')
        ->and($view)->toContain('Translation queue');
});

it('uses a blade-backed demo page content block for designed demo pages', function (): void {
    resolve(TypeCreator::class)->createBlockTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => 'About Us'],
        'title' => ['en' => 'About Us'],
    ], $site, createMedia: false);

    $page->refresh();

    expect($page->layout?->blocks)->toBe(['demo-page-hero', 'breadcrumbs', 'demo-page-content'])
        ->and(Block::query()->where('key', 'demo-page-content')->value('component'))->toBe('capell.block.demo-page-content')
        ->and(Block::query()->where('key', 'demo-page-content')->value('view_file'))->toBeNull()
        ->and(Block::query()->where('key', 'demo-page-hero')->value('component'))->toBe('capell.block.hero')
        ->and($page->translation?->content)->toContain('<p>Capell combines Laravel package discipline')
        ->and($page->translation?->content)->not->toContain('class=');
});

it('canonicalizes the old architecture demo page into the platform architecture layout', function (): void {
    resolve(TypeCreator::class)->createBlockTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => 'Home, Buildings and Architecture'],
        'title' => ['en' => 'Home, Buildings and Architecture'],
    ], $site, createMedia: false);

    $page->refresh()->loadMissing(['layout', 'translation']);

    expect($page->name)->toBe('Platform Architecture')
        ->and($page->layout?->key)->toBe('capell-demo-platform-architecture')
        ->and($page->layout?->blocks)->toContain('demo-page-hero')
        ->and($page->layout?->blocks)->toContain('demo-page-content')
        ->and($page->translation?->title)->toBe('Platform Architecture')
        ->and($page->translation?->getMeta('label'))->toBe('Platform Architecture')
        ->and($page->translation?->getMeta('slug'))->toBe('platform-architecture')
        ->and($page->translation?->getMeta('hero_title'))->toBe('Platform Architecture')
        ->and($page->translation?->getMeta('hero'))->toBeString()
        ->and($page->translation?->getMeta('hero'))->toStartWith('<p>')
        ->and(Block::query()->where('key', 'demo-page-hero')->value('component'))->toBe('capell.block.hero');
});

it('keeps demo pages without heroes on content-only layouts', function (): void {
    resolve(TypeCreator::class)->createBlockTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => 'Pricing'],
        'title' => ['en' => 'Pricing'],
    ], $site, createMedia: false);

    $page->refresh()->loadMissing(['layout', 'translation']);

    expect($page->layout?->key)->toBe('capell-demo-pricing-no-hero')
        ->and($page->layout?->blocks)->not->toContain('hero')
        ->and($page->layout?->blocks)->toContain('demo-page-content')
        ->and($page->translation?->getMeta('hero'))->toBeNull()
        ->and($page->translation?->getMeta('hero_title'))->toBe('Pricing');
});

it('seeds distinct page scoped assets for reusable demo page content block', function (): void {
    installContentSectionsForDemoAssets();
    resolve(TypeCreator::class)->createBlockTypes();

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

        $block = Block::query()->where('key', 'demo-page-content')->firstOrFail();
        $assets = BlockAsset::query()
            ->where('widget_id', $block->getKey())
            ->where('pageable_type', $page->getMorphClass())
            ->where('pageable_id', $page->getKey())
            ->where('container', 'main')
            ->where('occurrence', 1)
            ->get();

        expect($assets)->toHaveCount(1)
            ->and($assets->first()?->meta['variant'])->toBe($variant)
            ->and($assets->first()?->asset)->toBeInstanceOf(Section::class);
    }
});

it('seeds uniform contact routing content for designed contact pages', function (): void {
    $page = createDemoAssetPage('Contact');
    $block = Block::query()->where('key', 'demo-page-content')->firstOrFail();

    $asset = BlockAsset::query()
        ->where('widget_id', $block->getKey())
        ->where('pageable_type', $page->getMorphClass())
        ->where('pageable_id', $page->getKey())
        ->where('container', 'main')
        ->where('occurrence', 1)
        ->firstOrFail();

    expect($asset->meta['variant'])->toBe('contact-routing')
        ->and($asset->asset)->toBeInstanceOf(Section::class)
        ->and($asset->meta['title'])->toBe('Start the right conversation')
        ->and($asset->meta['items'])->toContain(['label' => 'Project scoping', 'title' => 'New implementations', 'copy' => 'Plan content models, package boundaries, layouts, and launch checks before the build starts.'])
        ->and($asset->meta['items'])->toContain(['label' => 'Migration planning', 'title' => 'Move from legacy CMSs', 'copy' => 'Map pages, redirects, media, structured fields, and verification work into a clear migration path.'])
        ->and($asset->meta['items'])->toContain(['label' => 'Partnerships', 'title' => 'Agency and technology work', 'copy' => 'Discuss delivery partnerships, packaged integrations, and repeatable theme or content operations.']);
});

it('keeps seeded demo page assets idempotent and preserves editor assets', function (): void {
    $page = createDemoAssetPage('Services');
    $block = Block::query()->where('key', 'demo-page-content')->firstOrFail();
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

    $block->assets()->create([
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

    $pageAssets = BlockAsset::query()
        ->where('widget_id', $block->getKey())
        ->where('pageable_type', $page->getMorphClass())
        ->where('pageable_id', $page->getKey())
        ->where('container', 'main')
        ->where('occurrence', 1)
        ->get();

    expect($pageAssets)->toHaveCount(2)
        ->and($pageAssets->where('meta.demo_kit_seed', true))->toHaveCount(1)
        ->and($pageAssets->where('asset_id', $editorAsset->getKey())->first()?->meta['editor_added'])->toBeTrue();
});

it('preloads asset backed demo page content without lazy loading block assets', function (): void {
    $page = createDemoAssetPage('Services');
    $language = Language::query()->where('default', true)->firstOrFail();
    $loadedBlock = resolve(LayoutLoader::class)->getLayoutBlock(
        $page->layout,
        'demo-page-content',
        $language,
        $page,
        'main',
        1,
    );

    expect($loadedBlock)->toBeInstanceOf(Block::class)
        ->and($loadedBlock?->relationLoaded('assets'))->toBeTrue()
        ->and($loadedBlock?->assets)->toHaveCount(1)
        ->and($loadedBlock?->assets->first()?->meta['variant'])->toBe('services-workbench');
});

it('renders asset backed demo page content without exposing layout metadata', function (): void {
    $page = createDemoAssetPage('Services');
    $language = Language::query()->where('default', true)->firstOrFail();
    $loadedBlock = resolve(LayoutLoader::class)->getLayoutBlock(
        $page->layout,
        'demo-page-content',
        $language,
        $page,
        'main',
        1,
    );
    $sections = resolve(DemoPageContentAssetSections::class)
        ->resolve($loadedBlock, $page, 'main', 1);
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

    $html = view('capell-demo-kit::components.block.demo-page-content-assets', [
        'sections' => $sections,
        ...$classes,
    ])->render();

    expect($html)->toContain('service-delivery-lanes')
        ->and($html)->toContain('Implementation services for complex Capell rollouts')
        ->and($html)->not->toContain('block_asset')
        ->and($html)->not->toContain('block_assets')
        ->and($html)->not->toContain('pageable_id')
        ->and($html)->not->toContain('pageable_type')
        ->and($html)->not->toContain('asset_id')
        ->and($html)->not->toContain('asset_type')
        ->and($html)->not->toContain('workspace_id')
        ->and($html)->not->toContain('Capell\\');
});

it('falls back when demo page assets are not preloaded', function (): void {
    $page = createDemoAssetPage('Services');
    $block = Block::query()->where('key', 'demo-page-content')->firstOrFail();

    $sections = resolve(DemoPageContentAssetSections::class)
        ->resolve($block, $page, 'main', 1);

    expect($block->relationLoaded('assets'))->toBeFalse()
        ->and($sections)->toBe([]);
});
