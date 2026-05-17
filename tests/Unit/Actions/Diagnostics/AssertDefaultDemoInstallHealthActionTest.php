<?php

declare(strict_types=1);

use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\DemoKit\Actions\Diagnostics\AssertDefaultDemoInstallHealthAction;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Models\Element;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;

beforeEach(function (): void {
    foreach (CapellLayoutBuilderManager::getMigrations() as $migration) {
        $instance = include dirname(__DIR__, 5) . '/layout-builder/database/migrations/' . $migration . '.php';

        $instance->up();
    }

    LayoutBuilderInstallPackageAction::run();
});

it('passes the showcase order asset and placeholder demo checks for curated homepage data', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $layout = createDemoHealthLayout($site, showcaseElementKeys());

    Page::factory()
        ->home()
        ->site($site)
        ->layout($layout)
        ->withTranslations($language, ['title' => 'Home'])
        ->create();

    foreach (showcaseElementKeys() as $key) {
        createDemoHealthElement($key, showcaseElementTitle($key));
    }

    $checks = AssertDefaultDemoInstallHealthAction::run()->checks->keyBy('label');

    expect($checks['Default demo showcase element order']->passed)->toBeTrue()
        ->and($checks['Default demo AP element assets']->passed)->toBeTrue()
        ->and($checks['Default demo placeholder labels']->passed)->toBeTrue();
});

it('fails when the homepage keeps generic AP labels or an incomplete showcase order', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $layout = createDemoHealthLayout($site, ['ap-card-grid', 'ap-hero-banner']);

    Page::factory()
        ->home()
        ->site($site)
        ->layout($layout)
        ->withTranslations($language, ['title' => 'Home'])
        ->create();

    createDemoHealthElement('ap-card-grid', 'AP Card Grid');

    $checks = AssertDefaultDemoInstallHealthAction::run()->checks->keyBy('label');

    expect($checks['Default demo showcase element order']->passed)->toBeFalse()
        ->and($checks['Default demo placeholder labels']->passed)->toBeFalse();
});

/**
 * @return list<string>
 */
function showcaseElementKeys(): array
{
    return [
        'capell-home-hero-command-center',
        'capell-home-proof-strip',
        'capell-home-demo-showcase',
        'capell-extension-marketplace-showcase',
        'capell-home-technical-pipeline',
        'capell-home-route-split',
        'capell-home-final-cta',
    ];
}

/**
 * @param  list<string>  $elementKeys
 */
function createDemoHealthLayout(Site $site, array $elementKeys): Layout
{
    return Layout::factory()
        ->site($site)
        ->create([
            'key' => 'home',
            'containers' => [
                'ap-elements' => [
                    'meta' => ['colspan' => 12],
                    'elements' => array_map(
                        fn (string $elementKey): array => ['element_key' => $elementKey],
                        $elementKeys,
                    ),
                ],
            ],
        ]);
}

function createDemoHealthElement(string $key, string $title): Element
{
    $type = Blueprint::factory()->create([
        'type' => LayoutTypeEnum::Element->value,
    ]);

    $element = Element::factory()
        ->for($type, 'type')
        ->create([
            'key' => $key,
            'name' => $title,
        ]);

    Translation::factory()
        ->translatable($element)
        ->create([
            'language_id' => Language::query()->firstOrFail()->id,
            'title' => $title,
            'content' => sprintf('<p>%s content</p>', $title),
        ]);

    return $element;
}

function showcaseElementTitle(string $key): string
{
    return [
        'capell-home-hero-command-center' => 'Capell CMS',
        'capell-home-proof-strip' => 'Proof points for a healthier release',
        'capell-home-demo-showcase' => 'A complete CMS foundation',
        'capell-extension-marketplace-showcase' => 'Extension marketplace showcase',
        'capell-home-technical-pipeline' => 'Everything visible is backed by editable records',
        'capell-home-route-split' => 'From model to public page',
        'capell-home-final-cta' => 'A demo site that proves the CMS stack is wired',
    ][$key];
}

function createElementAssets(string $elementKey, int $count): void
{
    $element = Element::query()->where('key', $elementKey)->firstOrFail();

    for ($index = 0; $index < $count; $index++) {
        resolve(ConnectionResolverInterface::class)->table('layout_element_assets')->insert([
            'layout_element_id' => $element->id,
            'asset_type' => Page::query()->make()->getMorphClass(),
            'asset_id' => (string) Str::uuid(),
            'order' => $index + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
