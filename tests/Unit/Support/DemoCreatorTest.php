<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Enums\ElementComponentEnum;
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
        ->and($element->component)->toBe(ElementComponentEnum::Default->value)
        ->and($element->getViewFile())->toBe('capell-demo-kit::components.element.homepage-section');
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

    expect($page->layout?->elements)->toBe(['breadcrumbs', 'demo-page-content'])
        ->and(Element::query()->where('key', 'demo-page-content')->value('component'))->toBe(ElementComponentEnum::PageContent->value)
        ->and($page->translation?->content)->toContain('<p>Capell combines Laravel package discipline')
        ->and($page->translation?->content)->not->toContain('class=');
});
