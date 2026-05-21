<?php

declare(strict_types=1);

use Capell\Core\Data\AssetData;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\DemoKit\LayoutBuilder\Actions\CreateLayoutBuilderDemoSiteAction;
use Capell\DemoKit\LayoutBuilder\Data\DemoSitePlanData;
use Capell\DemoKit\Tests\Fixtures\Models\DemoAsset;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Models\Block;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    foreach (CapellLayoutBuilderManager::getMigrations() as $migration) {
        $instance = include dirname(__DIR__, 4) . '/layout-builder/database/migrations/' . $migration . '.php';

        $instance->up();
    }

    LayoutBuilderInstallPackageAction::run();
    resolve(BlueprintCreator::class)->createPageTypes();
    resolve(TypeCreator::class)->createBlockTypes();

    Schema::create('demo_assets', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('site_id')->nullable();
        $table->unsignedBigInteger('blueprint_id')->nullable();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
    });

    CapellCore::registerAsset(new AssetData(
        name: 'Section',
        model: DemoAsset::class,
        hasTranslations: true,
    ));
    Relation::morphMap(['demo_asset' => DemoAsset::class], merge: true);
});

it('returns false when a site has no pageable homepage', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();

    $created = CreateLayoutBuilderDemoSiteAction::run(new DemoSitePlanData(
        site: $site,
        contentTree: demoSiteActionContentTree(),
    ));

    expect($created)->toBeFalse()
        ->and(DemoAsset::query()->where('site_id', $site->getKey())->where('name', 'Root')->exists())->toBeTrue();
});

it('builds homepage showcase layout blocks and nested content tree pages', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $layout = Layout::query()->firstWhere('key', LayoutEnum::Home)
        ?? Layout::factory()->site($site)->create(['key' => LayoutEnum::Home]);
    $layout->forceFill([
        'site_id' => $site->getKey(),
        'containers' => [
            'secondary' => [
                'meta' => ['colspan' => 4],
                'blocks' => [['block_key' => 'existing-secondary']],
            ],
            'loose' => [
                'meta' => ['colspan' => 12],
                'blocks' => 'not-a-block-list',
            ],
        ],
        'blocks' => ['existing-secondary'],
    ])->save();

    $homeType = Blueprint::query()->pageType()->where('key', 'home')->firstOrFail();
    $homePage = Page::factory()
        ->site($site)
        ->type($homeType)
        ->layout($layout)
        ->withTranslations($language)
        ->create(['name' => 'Home', 'parent_id' => null, 'order' => 1]);

    $created = CreateLayoutBuilderDemoSiteAction::run(new DemoSitePlanData(
        site: $site,
        contentTree: demoSiteActionContentTree(),
    ));

    $layout->refresh();

    $rootContent = DemoAsset::query()->where('site_id', $site->getKey())->where('name', 'Root')->firstOrFail();
    $childContent = DemoAsset::query()->where('site_id', $site->getKey())->where('name', 'Child')->firstOrFail();

    expect($created)->toBeTrue()
        ->and($homePage->refresh()->layout_id)->toBe($layout->getKey())
        ->and(array_keys($layout->containers))->toBe(['ap-blocks', 'loose'])
        ->and($layout->blocks)->toBe([
            'capell-home-hero-command-center',
            'capell-home-proof-strip',
            'capell-home-demo-showcase',
            'capell-extension-marketplace-showcase',
            'capell-home-technical-pipeline',
            'capell-home-route-split',
            'capell-home-final-cta',
        ])
        ->and(Block::query()->whereIn('key', $layout->blocks)->count())->toBe(7)
        ->and($childContent->parent_id)->toBe($rootContent->getKey());
});

/**
 * @return array<string, mixed>
 */
function demoSiteActionContentTree(): array
{
    return [
        'name' => [
            'en' => 'Root',
            'fr' => 'Racine',
        ],
        'children' => [
            [
                'name' => [
                    'en' => 'Child',
                ],
            ],
        ],
    ];
}
