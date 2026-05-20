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
use Capell\LayoutBuilder\Models\Block;
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
    $layout = createDemoHealthLayout($site, showcaseBlockKeys());

    Page::factory()
        ->home()
        ->site($site)
        ->layout($layout)
        ->withTranslations($language, ['title' => 'Home'])
        ->create();

    foreach (showcaseBlockKeys() as $key) {
        createDemoHealthBlock($key, showcaseBlockTitle($key));
    }

    $checks = AssertDefaultDemoInstallHealthAction::run()->checks->keyBy('label');

    expect($checks['Default demo showcase block order']->passed)->toBeTrue()
        ->and($checks['Default demo AP block assets']->passed)->toBeTrue()
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

    createDemoHealthBlock('ap-card-grid', 'AP Card Grid');

    $checks = AssertDefaultDemoInstallHealthAction::run()->checks->keyBy('label');

    expect($checks['Default demo showcase block order']->passed)->toBeFalse()
        ->and($checks['Default demo placeholder labels']->passed)->toBeFalse();
});

/**
 * @return list<string>
 */
function showcaseBlockKeys(): array
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
 * @param  list<string>  $blockKeys
 */
function createDemoHealthLayout(Site $site, array $blockKeys): Layout
{
    return Layout::factory()
        ->site($site)
        ->create([
            'key' => 'home',
            'containers' => [
                'ap-blocks' => [
                    'meta' => ['colspan' => 12],
                    'blocks' => array_map(
                        fn (string $blockKey): array => ['block_key' => $blockKey],
                        $blockKeys,
                    ),
                ],
            ],
        ]);
}

function createDemoHealthBlock(string $key, string $title): Block
{
    $type = Blueprint::factory()->create([
        'type' => LayoutTypeEnum::Block->value,
    ]);

    $block = Block::factory()
        ->for($type, 'type')
        ->create([
            'key' => $key,
            'name' => $title,
        ]);

    Translation::factory()
        ->translatable($block)
        ->create([
            'language_id' => Language::query()->firstOrFail()->id,
            'title' => $title,
            'content' => sprintf('<p>%s content</p>', $title),
        ]);

    return $block;
}

function showcaseBlockTitle(string $key): string
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

function createBlockAssets(string $blockKey, int $count): void
{
    $block = Block::query()->where('key', $blockKey)->firstOrFail();

    for ($index = 0; $index < $count; $index++) {
        resolve(ConnectionResolverInterface::class)->table('block_assets')->insert([
            'block_id' => $block->id,
            'asset_type' => Page::query()->make()->getMorphClass(),
            'asset_id' => (string) Str::uuid(),
            'order' => $index + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
