<?php

declare(strict_types=1);

use Capell\Core\Actions\Content\ExtractTextContentAction;
use Capell\Core\Data\AssetData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\DemoKit\Support\Creator\DemoResourceResolver;
use Capell\DemoKit\Tests\Fixtures\Models\DemoAsset;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

    Queue::fake();
    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');
    config()->set('media-library.conversions_disk', 'public');

    bindDemoWidgetCreatorTinyResources();
});

it('creates standard content widgets with translated portable content', function (): void {
    $language = Language::factory()->default()->create(['code' => 'en']);
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();
    $pageType = Blueprint::query()->pageType()->default()->firstOrFail();

    Page::factory()
        ->site($site)
        ->type($pageType)
        ->withTranslations($language, ['title' => 'Related Page'])
        ->create();

    $creator = new DemoCreator;

    $contentWidget = $creator->createContentWidget($site->languages);
    $splitWidget = $creator->createSplitContentWidget($site->languages);
    $contentWidgetContent = $contentWidget->translations()->first()?->content;
    $splitWidgetContent = $splitWidget->translations()->first()?->content;

    expect($contentWidget)->toBeInstanceOf(Widget::class)
        ->and($contentWidget->key)->toBe('example-content')
        ->and($contentWidget->translations)->toHaveCount(1)
        ->and($contentWidgetContent)->toBeArray()
        ->and(ExtractTextContentAction::run($contentWidgetContent))->not->toBe('')
        ->and($splitWidget->key)->toBe('example-split-content')
        ->and($splitWidgetContent)->toBeArray()
        ->and(ExtractTextContentAction::run($splitWidgetContent))->not->toBe('');
});

it('creates asset backed standard demo widgets idempotently', function (): void {
    $language = Language::factory()->default()->create(['code' => 'en']);
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $creator = new DemoCreator;

    $gallery = $creator->createGalleryWidget();
    $carousel = $creator->createMediaCarouselWidget();
    $logos = $creator->createClientLogosWidget($site->languages);
    $statistics = $creator->createStatisticsWidget();
    $secondStatistics = $creator->createStatisticsWidget();

    expect($gallery->assets()->count())->toBe(5)
        ->and($creator->createGalleryWidget()->assets()->count())->toBe(5)
        ->and($carousel->assets()->count())->toBe(8)
        ->and($logos->key)->toBe('client-logos')
        ->and($logos->assets()->count())->toBe(12)
        ->and($statistics->key)->toBe('statistics')
        ->and($statistics->assets()->count())->toBe(4)
        ->and($secondStatistics->assets()->count())->toBe(4);
});

it('creates page card assets only when related image pages exist', function (): void {
    $language = Language::factory()->default()->create(['code' => 'en']);
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();
    $pageType = Blueprint::query()->pageType()->default()->firstOrFail();
    $creator = new DemoCreator;

    $page = Page::factory()
        ->site($site)
        ->type($pageType)
        ->withTranslations($language, ['title' => 'Current'])
        ->create();

    $emptyWidget = $creator->createPageCardsWidget($page);

    expect($emptyWidget->assets()->count())->toBe(0);

    for ($counter = 1; $counter <= 3; $counter++) {
        $relatedPage = Page::factory()
            ->site($site)
            ->type($pageType)
            ->withTranslations($language, ['title' => 'Related ' . $counter])
            ->create();

        $creator->createMedia($relatedPage, 'demo-' . $counter);
    }

    $cardsWidget = $creator->createPageCardsWidget($page, 'sidebar', 2);
    $duplicateCardsWidget = $creator->createPageCardsWidget($page, 'sidebar', 2);

    expect($cardsWidget->assets()->count())->toBe(3)
        ->and($duplicateCardsWidget->assets()->count())->toBe(3)
        ->and(WidgetAsset::query()->where('pageable_id', $page->getKey())->where('container', 'sidebar')->count())->toBe(3);
});

it('creates app showcase demo widgets with sections and media assets', function (): void {
    $language = Language::factory()->default()->create(['code' => 'en']);
    Site::factory()->language($language)->default()->withTranslations($language)->create();

    $creator = new DemoCreator;

    $hero = $creator->createApHeroBannerWidget();
    $cards = $creator->createApCardGridWidget();
    $features = $creator->createApFeatureListWidget();
    $genericFeatures = $creator->createFeatureListWidget();
    $cta = $creator->createApCtaSectionWidget();
    $gallery = $creator->createApImageGalleryWidget();

    expect($hero->key)->toBe('ap-hero-banner')
        ->and($cards->assets()->count())->toBe(3)
        ->and($features->assets()->count())->toBe(4)
        ->and($genericFeatures->assets()->count())->toBe(6)
        ->and($cta->translations()->count())->toBe(1)
        ->and($gallery->assets()->count())->toBe(6)
        ->and($creator->createApImageGalleryWidget()->assets()->count())->toBe(6);
});

it('creates modern demo widgets as repeatable section-backed widgets', function (): void {
    $language = Language::factory()->default()->create(['code' => 'en']);
    Site::factory()->language($language)->default()->withTranslations($language)->create();

    $creator = new DemoCreator;

    $featureList = $creator->createModernFeatureListWidget();
    $teamMembers = $creator->createModernTeamMembersWidget();
    $pricing = $creator->createModernPricingTableWidget();
    $testimonials = $creator->createModernTestimonialsWidget();
    $faq = $creator->createModernFaqWidget();
    $stats = $creator->createModernStatsSectionWidget();
    $alternating = $creator->createModernAlternatingContentWidget();
    $process = $creator->createModernProcessStepsWidget();
    $gallery = $creator->createModernImageGalleryWidget();

    expect($featureList->assets()->count())->toBe(6)
        ->and($teamMembers->assets()->count())->toBe(3)
        ->and($pricing->assets()->count())->toBe(3)
        ->and($testimonials->assets()->count())->toBe(3)
        ->and($faq->assets()->count())->toBe(4)
        ->and($stats->assets()->count())->toBe(4)
        ->and($alternating->assets()->count())->toBe(3)
        ->and($process->assets()->count())->toBe(4)
        ->and($gallery->assets()->count())->toBe(6)
        ->and($creator->createModernTeamMembersWidget()->assets()->count())->toBe(3);
});

it('creates the remaining standard section-backed demo widgets idempotently', function (): void {
    $language = Language::factory()->default()->create(['code' => 'en']);
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();
    $pageType = Blueprint::query()->pageType()->default()->firstOrFail();
    $page = Page::factory()
        ->site($site)
        ->type($pageType)
        ->withTranslations($language, ['title' => 'Current'])
        ->create();

    Page::factory()
        ->count(4)
        ->site($site)
        ->type($pageType)
        ->withTranslations($language)
        ->create();

    $creator = new DemoCreator;

    $faq = $creator->createFaqWidget($site->languages);
    $navigation = $creator->createStaticNavigationWidget($site->languages, $site);
    $contentsWidget = $creator->createFeatureListWidget();
    $creator->createContentsWidget($contentsWidget, $page, 'main', 3);
    $creator->createContentsWidget($contentsWidget, $page, 'main', 3);

    $businessFeatures = $creator->createBusinessFeaturesWidget($site);
    $banners = $creator->createBannersWidget();
    $testimonials = $creator->createTestimonialsWidget($site->languages);
    $teamPortfolio = $creator->createTeamPortfolioWidget($site->languages);
    $navigationMeta = is_array($navigation->meta) ? $navigation->meta : [];

    expect($faq->assets()->count())->toBe(6)
        ->and($navigationMeta['navigation'] ?? null)->toBe('example-menu')
        ->and($contentsWidget->assets()->where('pageable_id', $page->getKey())->where('container', 'main')->count())->toBe(4)
        ->and($businessFeatures->assets()->count())->toBe(7)
        ->and($banners->assets()->count())->toBe(7)
        ->and($testimonials->assets()->count())->toBe(3)
        ->and($teamPortfolio->assets()->count())->toBe(16)
        ->and(DemoAsset::query()->where('name', 'FAQs')->exists())->toBeTrue()
        ->and(DemoAsset::query()->where('name', 'Team Members')->exists())->toBeTrue();
});

it('creates faq widgets for languages without explicit demo questions', function (): void {
    $language = Language::factory()->default()->create(['code' => 'nl']);
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $creator = new DemoCreator;

    $faq = $creator->createFaqWidget($site->languages);

    expect($faq->assets()->count())->toBe(6)
        ->and(DemoAsset::query()->where('name', 'How was this website created?')->exists())->toBeTrue();
});

function bindDemoWidgetCreatorTinyResources(): void
{
    $demoDirectory = sys_get_temp_dir() . '/capell-demo-widget-creator-resources-' . uniqid();
    $imageDirectory = $demoDirectory . '/img';
    $videoDirectory = $demoDirectory . '/video';

    File::ensureDirectoryExists($imageDirectory);
    File::ensureDirectoryExists($videoDirectory);

    $image = imagecreatetruecolor(32, 32);
    assert($image instanceof GdImage);

    $background = imagecolorallocate($image, 72, 99, 132);
    assert(is_int($background));

    imagefilledrectangle($image, 0, 0, 31, 31, $background);

    foreach (['demo-1', 'demo-2', 'demo-3', 'example-content', 'example-split-content', 'banner-image'] as $imageName) {
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
