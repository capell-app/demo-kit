<?php

declare(strict_types=1);

use Capell\Core\Data\AssetData;
use Capell\Core\Enums\ContainerWidthEnum;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Media;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\Core\Support\TableName;
use Capell\DemoKit\Actions\CreateFoundationShowcaseArticleAction;
use Capell\DemoKit\Actions\CreateFoundationShowcaseBookingAction;
use Capell\DemoKit\Actions\CreateFoundationShowcaseMediaAction;
use Capell\DemoKit\Actions\CreateFoundationShowcaseVisitAction;
use Capell\DemoKit\LayoutBuilder\Actions\CreateLayoutBuilderDemoSiteAction;
use Capell\DemoKit\LayoutBuilder\Data\DemoSitePlanData;
use Capell\DemoKit\Tests\Fixtures\Models\DemoAsset;
use Capell\FormBuilder\Models\Form;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    foreach (CapellLayoutBuilderManager::getMigrations() as $migration) {
        $instance = include dirname(__DIR__, 4) . '/layout-builder/database/migrations/' . $migration . '.php';

        $instance->up();
    }

    LayoutBuilderInstallPackageAction::run();
    resolve(BlueprintCreator::class)->createPageTypes();
    resolve(TypeCreator::class)->createWidgetTypes();

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

it('builds homepage showcase layout widgets and nested content tree pages', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $layout = Layout::query()->firstWhere('key', LayoutEnum::Home)
        ?? Layout::factory()->site($site)->create(['key' => LayoutEnum::Home]);
    $layout->forceFill([
        'site_id' => $site->getKey(),
        'containers' => [
            'secondary' => [
                'meta' => ['colspan' => 4],
                'widgets' => [['widget_key' => 'existing-secondary']],
            ],
            'hero' => [
                'meta' => ['colspan' => 12],
                'widgets' => [['widget_key' => 'hero']],
            ],
            'loose' => [
                'meta' => ['colspan' => 12],
                'widgets' => 'not-a-widget-list',
            ],
        ],
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
        ->and(array_keys($layout->containers))->toBe(['hero', 'ap-widgets', 'final-cta', 'loose'])
        ->and($layout->containers['hero']['meta']['container'])->toBe(ContainerWidthEnum::Full->value)
        ->and($layout->containers['hero']['widgets'])->toBe([
            ['widget_key' => 'capell-home-hero-command-center'],
        ])
        ->and($layout->containers['final-cta']['meta']['container'])->toBe(ContainerWidthEnum::Full->value)
        ->and($layout->containers['final-cta']['widgets'])->toBe([
            ['widget_key' => 'capell-home-final-cta'],
        ])
        ->and($layout->widgets)->toBe([
            'capell-home-hero-command-center',
            'capell-home-proof-strip',
            'capell-home-demo-showcase',
            'capell-home-demo-widgets-carousel',
            'capell-extension-marketplace-showcase',
            'capell-home-technical-pipeline',
            'capell-home-route-split',
            'capell-home-final-cta',
        ])
        ->and(Widget::query()->whereIn('key', $layout->widgets)->count())->toBe(8)
        ->and($childContent->parent_id)->toBe($rootContent->getKey());
});

it('builds nested content tree pages without english names', function (): void {
    $language = Language::factory()->french(isDefault: true)->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $layout = Layout::query()->firstWhere('key', LayoutEnum::Home)
        ?? Layout::factory()->site($site)->create(['key' => LayoutEnum::Home]);

    $homeType = Blueprint::query()->pageType()->where('key', 'home')->firstOrFail();
    Page::factory()
        ->site($site)
        ->type($homeType)
        ->layout($layout)
        ->withTranslations($language)
        ->create(['name' => 'Accueil', 'parent_id' => null, 'order' => 1]);

    $created = CreateLayoutBuilderDemoSiteAction::run(new DemoSitePlanData(
        site: $site,
        contentTree: [
            'name' => ['fr' => 'Racine'],
            'children' => [
                ['name' => ['fr' => 'Enfant']],
            ],
        ],
    ));

    $rootContent = DemoAsset::query()->where('site_id', $site->getKey())->where('name', 'Racine')->firstOrFail();
    $childContent = DemoAsset::query()->where('site_id', $site->getKey())->where('name', 'Enfant')->firstOrFail();

    expect($created)->toBeTrue()
        ->and($childContent->parent_id)->toBe($rootContent->getKey());
});

/**
 * @return array<string, mixed>
 */
it('seeds the Foundation showcase article once on the default site', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $homeLayout = Layout::query()->firstWhere('key', LayoutEnum::Home)
        ?? Layout::factory()->site($site)->create(['key' => LayoutEnum::Home]);
    Layout::query()->firstWhere('key', LayoutEnum::Default)
        ?? Layout::factory()->site($site)->create(['key' => LayoutEnum::Default]);

    $homeType = Blueprint::query()->pageType()->where('key', 'home')->firstOrFail();
    Page::factory()
        ->site($site)
        ->type($homeType)
        ->layout($homeLayout)
        ->withTranslations($language)
        ->create(['name' => 'Home', 'parent_id' => null, 'order' => 1]);

    $plan = new DemoSitePlanData(site: $site, contentTree: demoSiteActionContentTree());

    CreateLayoutBuilderDemoSiteAction::run($plan);
    CreateLayoutBuilderDemoSiteAction::run($plan);

    $articles = Page::query()
        ->where('site_id', $site->getKey())
        ->where('meta->demo_fixture', CreateFoundationShowcaseArticleAction::Fixture)
        ->with('pageUrl')
        ->get();

    expect($articles)->toHaveCount(1)
        ->and($articles->first()?->pageUrl?->url)->toEndWith('/' . CreateFoundationShowcaseArticleAction::Slug);
});

it('seeds the Foundation showcase article in every site language rather than falling back to English', function (): void {
    $english = Language::factory()->english()->create();
    $languages = [
        $english,
        Language::factory()->french()->create(),
        Language::factory()->german()->create(),
        Language::factory()->forCountry('العربية', 'ar', 'ar', 'sa', order: 4)->create(),
    ];
    $site = Site::factory()->default()->language($english)->withTranslations($languages)->create();
    Layout::query()->firstWhere('key', LayoutEnum::Default)
        ?? Layout::factory()->site($site)->create(['key' => LayoutEnum::Default]);

    $article = CreateFoundationShowcaseArticleAction::run($site);

    expect($article)->toBeInstanceOf(Page::class);
    assert($article instanceof Page);

    $titles = $article->translations()->with('language')->get()
        ->mapWithKeys(fn ($translation): array => [(string) $translation->language?->code => (string) $translation->title])
        ->all();

    expect($titles)->toBe([
        'en' => 'The estuary at first light',
        'fr' => 'L’estuaire au petit jour',
        'de' => 'Die Flussmündung im ersten Licht',
        'ar' => 'مصب النهر عند أول ضوء',
    ]);
});

it('seeds the Foundation showcase visit section once, with both child pages under it', function (): void {
    $plan = showcaseFixtureSitePlan();

    CreateLayoutBuilderDemoSiteAction::run($plan);
    CreateLayoutBuilderDemoSiteAction::run($plan);

    $parents = showcaseFixturePages($plan->site, CreateFoundationShowcaseVisitAction::Fixture);
    $children = Page::query()
        ->where('site_id', $plan->site->getKey())
        ->whereIn('meta->demo_fixture', [
            CreateFoundationShowcaseVisitAction::GettingHereFixture,
            CreateFoundationShowcaseVisitAction::TideTimesFixture,
        ])
        ->get();

    expect($parents)->toHaveCount(1)
        ->and($parents->first()?->pageUrl?->url)->toEndWith('/' . CreateFoundationShowcaseVisitAction::Slug)
        ->and($children)->toHaveCount(2)
        ->and($children->pluck('parent_id')->unique()->all())->toBe([$parents->first()?->getKey()]);
});

it('seeds the Foundation showcase photo story with its focal point and alt text', function (): void {
    $plan = showcaseFixtureSitePlan();

    CreateLayoutBuilderDemoSiteAction::run($plan);
    CreateLayoutBuilderDemoSiteAction::run($plan);

    $pages = showcaseFixturePages($plan->site, CreateFoundationShowcaseMediaAction::Fixture);
    $page = $pages->first();

    throw_unless($page instanceof Page);

    $media = $page->getMedia('image');
    $image = $media->first();

    throw_unless($image instanceof Media);

    expect($pages)->toHaveCount(1)
        ->and($media)->toHaveCount(1)
        ->and($image->getFocalPoint())->toBe(CreateFoundationShowcaseMediaAction::FocalPoint)
        ->and($image->getAltText($plan->site->languages->first()))->not->toBeEmpty();
});

it('seeds the Foundation showcase booking page with its form under the page copy', function (): void {
    $plan = showcaseFixtureSitePlan();

    CreateLayoutBuilderDemoSiteAction::run($plan);
    CreateLayoutBuilderDemoSiteAction::run($plan);

    $pages = showcaseFixturePages($plan->site, CreateFoundationShowcaseBookingAction::Fixture);
    $layout = Layout::query()->firstWhere('key', CreateFoundationShowcaseBookingAction::LayoutKey);
    $defaultContainers = Layout::query()->firstWhere('key', LayoutEnum::Default)?->containers;
    $widget = Widget::query()->where('key', CreateFoundationShowcaseBookingAction::WidgetKey)->firstOrFail();

    throw_unless($layout instanceof Layout && is_array($defaultContainers));

    $containers = $layout->containers;

    throw_unless(is_array($containers) && is_array($containers[CreateFoundationShowcaseBookingAction::FormContainer] ?? null));

    expect($pages)->toHaveCount(1)
        ->and($pages->first()?->layout_id)->toBe($layout->getKey())
        ->and(array_keys($containers))->toBe([...array_keys($defaultContainers), CreateFoundationShowcaseBookingAction::FormContainer])
        ->and($containers[CreateFoundationShowcaseBookingAction::FormContainer]['widgets'] ?? null)->toBe([
            ['widget_key' => CreateFoundationShowcaseBookingAction::WidgetKey],
        ])
        ->and($widget->component)->toBe('capell-form-builder::widget.form')
        ->and($widget->meta)->toMatchArray(['form_handle' => CreateFoundationShowcaseBookingAction::FormHandle])
        ->and(Form::query()
            ->where('site_id', $plan->site->getKey())
            ->where('handle', CreateFoundationShowcaseBookingAction::FormHandle)
            ->count())->toBe(1);
});

it('skips the Foundation showcase booking page when Form Builder is not installed', function (): void {
    // Form Builder's code is present in the monorepo, so isPackageAvailable() is
    // true; a site that has not installed it has no forms table, which is the
    // second guard the action relies on.
    Schema::dropIfExists(TableName::resolve('forms'));

    $plan = showcaseFixtureSitePlan();

    CreateLayoutBuilderDemoSiteAction::run($plan);

    expect(showcaseFixturePages($plan->site, CreateFoundationShowcaseBookingAction::Fixture))->toBeEmpty()
        ->and(Layout::query()->where('key', CreateFoundationShowcaseBookingAction::LayoutKey)->exists())->toBeFalse()
        ->and(showcaseFixturePages($plan->site, CreateFoundationShowcaseVisitAction::Fixture))->toHaveCount(1);
});

function showcaseFixtureSitePlan(): DemoSitePlanData
{
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $homeLayout = Layout::query()->firstWhere('key', LayoutEnum::Home)
        ?? Layout::factory()->site($site)->create(['key' => LayoutEnum::Home]);
    Layout::query()->firstWhere('key', LayoutEnum::Default)
        ?? Layout::factory()->site($site)->create(['key' => LayoutEnum::Default]);

    $homeType = Blueprint::query()->pageType()->where('key', 'home')->firstOrFail();
    Page::factory()
        ->site($site)
        ->type($homeType)
        ->layout($homeLayout)
        ->withTranslations($language)
        ->create(['name' => 'Home', 'parent_id' => null, 'order' => 1]);

    return new DemoSitePlanData(site: $site, contentTree: demoSiteActionContentTree());
}

/** @return Collection<int, Page> */
function showcaseFixturePages(Site $site, string $fixture): Collection
{
    return Page::query()
        ->where('site_id', $site->getKey())
        ->where('meta->demo_fixture', $fixture)
        ->with('pageUrl')
        ->get();
}

/** @return array<string, mixed> */
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
