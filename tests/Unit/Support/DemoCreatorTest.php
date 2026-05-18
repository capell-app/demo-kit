<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Models\Block;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;

beforeEach(function (): void {
    foreach (CapellLayoutBuilderManager::getMigrations() as $migration) {
        $instance = include dirname(__DIR__, 4) . '/layout-builder/database/migrations/' . $migration . '.php';

        $instance->up();
    }

    LayoutBuilderInstallPackageAction::run();
});

it('creates homepage demo snippets as layout builder blocks', function (): void {
    resolve(TypeCreator::class)->createBlockTypes();

    $block = resolve(DemoCreator::class)->createHomepageHeroCommandCenterBlock();

    expect($block)->toBeInstanceOf(Block::class)
        ->and($block->getTable())->toBe('blocks')
        ->and($block->key)->toBe('capell-home-hero-command-center')
        ->and($block->component)->toBe(DemoKitServiceProvider::HomepageSectionRenderable)
        ->and($block->getViewFile())->toBeNull();
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
