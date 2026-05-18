<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Models\Element;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;

beforeEach(function (): void {
    foreach (CapellLayoutBuilderManager::getMigrations() as $migration) {
        $instance = include dirname(__DIR__, 4) . '/layout-builder/database/migrations/' . $migration . '.php';

        $instance->up();
    }

    LayoutBuilderInstallPackageAction::run();
});

it('creates homepage demo snippets as layout builder elements', function (): void {
    resolve(TypeCreator::class)->createElementTypes();

    $element = resolve(DemoCreator::class)->createHomepageHeroCommandCenterElement();

    expect($element)->toBeInstanceOf(Element::class)
        ->and($element->getTable())->toBe('elements')
        ->and($element->key)->toBe('capell-home-hero-command-center')
        ->and($element->component)->toBe(DemoKitServiceProvider::HomepageSectionRenderable)
        ->and($element->getViewFile())->toBeNull();
});

it('uses a blade-backed demo page content element for designed demo pages', function (): void {
    resolve(TypeCreator::class)->createElementTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => 'About Us'],
        'title' => ['en' => 'About Us'],
    ], $site, createMedia: false);

    $page->refresh();

    expect($page->layout?->elements)->toBe(['demo-page-hero', 'breadcrumbs', 'demo-page-content'])
        ->and(Element::query()->where('key', 'demo-page-content')->value('component'))->toBe('capell.element.demo-page-content')
        ->and(Element::query()->where('key', 'demo-page-content')->value('view_file'))->toBeNull()
        ->and(Element::query()->where('key', 'demo-page-hero')->value('component'))->toBe('capell.element.hero')
        ->and($page->translation?->content)->toContain('<p>Capell combines Laravel package discipline')
        ->and($page->translation?->content)->not->toContain('class=');
});

it('canonicalizes the old architecture demo page into the platform architecture layout', function (): void {
    resolve(TypeCreator::class)->createElementTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => 'Home, Buildings and Architecture'],
        'title' => ['en' => 'Home, Buildings and Architecture'],
    ], $site, createMedia: false);

    $page->refresh()->loadMissing(['layout', 'translation']);

    expect($page->name)->toBe('Platform Architecture')
        ->and($page->layout?->key)->toBe('capell-demo-platform-architecture')
        ->and($page->layout?->elements)->toContain('demo-page-hero')
        ->and($page->layout?->elements)->toContain('demo-page-content')
        ->and($page->translation?->title)->toBe('Platform Architecture')
        ->and($page->translation?->getMeta('label'))->toBe('Platform Architecture')
        ->and($page->translation?->getMeta('slug'))->toBe('platform-architecture')
        ->and($page->translation?->getMeta('hero_title'))->toBe('Platform Architecture')
        ->and($page->translation?->getMeta('hero'))->toBeString()
        ->and($page->translation?->getMeta('hero'))->toStartWith('<p>')
        ->and(Element::query()->where('key', 'demo-page-hero')->value('component'))->toBe('capell.element.hero');
});

it('keeps demo pages without heroes on content-only layouts', function (): void {
    resolve(TypeCreator::class)->createElementTypes();

    $language = Language::factory()->default()->create();
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $page = resolve(DemoCreator::class)->createPage([
        'name' => ['en' => 'Pricing'],
        'title' => ['en' => 'Pricing'],
    ], $site, createMedia: false);

    $page->refresh()->loadMissing(['layout', 'translation']);

    expect($page->layout?->key)->toBe('capell-demo-pricing-no-hero')
        ->and($page->layout?->elements)->not->toContain('hero')
        ->and($page->layout?->elements)->toContain('demo-page-content')
        ->and($page->translation?->getMeta('hero'))->toBeNull()
        ->and($page->translation?->getMeta('hero_title'))->toBe('Pricing');
});
