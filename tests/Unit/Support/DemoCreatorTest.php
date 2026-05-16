<?php

declare(strict_types=1);

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

    $element = resolve(DemoCreator::class)->createHomepageHeroCommandCenterWidget();

    expect($element)->toBeInstanceOf(Element::class)
        ->and($element->getTable())->toBe('elements')
        ->and($element->key)->toBe('capell-home-hero-command-center')
        ->and($element->component)->toBe(ElementComponentEnum::Snippet->value);
});
