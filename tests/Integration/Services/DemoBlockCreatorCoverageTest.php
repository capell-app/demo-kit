<?php

declare(strict_types=1);

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
use Capell\LayoutBuilder\Models\Block;
use Capell\LayoutBuilder\Models\BlockAsset;
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

    Queue::fake();
    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');
    config()->set('media-library.conversions_disk', 'public');

    bindDemoBlockCreatorTinyResources();
});

it('creates standard content blocks with translated portable content', function (): void {
    $language = Language::factory()->default()->create(['code' => 'en']);
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();
    $pageType = Blueprint::query()->pageType()->default()->firstOrFail();

    Page::factory()
        ->site($site)
        ->type($pageType)
        ->withTranslations($language, ['title' => 'Related Page'])
        ->create();

    $creator = new DemoCreator;

    $contentBlock = $creator->createContentBlock($site->languages);
    $splitBlock = $creator->createSplitContentBlock($site->languages);
    expect($contentBlock)->toBeInstanceOf(Block::class)
        ->and($contentBlock->key)->toBe('example-content')
        ->and($contentBlock->translations)->toHaveCount(1)
        ->and(json_decode((string) $contentBlock->translations()->first()?->content, true))->toBeArray()
        ->and($splitBlock->key)->toBe('example-split-content')
        ->and(json_decode((string) $splitBlock->translations()->first()?->content, true))->toBeArray();
});

it('creates asset backed standard demo blocks idempotently', function (): void {
    $language = Language::factory()->default()->create(['code' => 'en']);
    $site = Site::factory()->language($language)->default()->withTranslations($language)->create();

    $creator = new DemoCreator;

    $gallery = $creator->createGalleryBlock();
    $carousel = $creator->createMediaCarouselBlock();
    $logos = $creator->createClientLogosBlock($site->languages);
    $statistics = $creator->createStatisticsBlock();
    $secondStatistics = $creator->createStatisticsBlock();

    expect($gallery->assets()->count())->toBe(5)
        ->and($creator->createGalleryBlock()->assets()->count())->toBe(5)
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

    $emptyBlock = $creator->createPageCardsBlock($page);

    expect($emptyBlock->assets()->count())->toBe(0);

    for ($counter = 1; $counter <= 3; $counter++) {
        $relatedPage = Page::factory()
            ->site($site)
            ->type($pageType)
            ->withTranslations($language, ['title' => 'Related ' . $counter])
            ->create();

        $creator->createMedia($relatedPage, 'demo-' . $counter);
    }

    $cardsBlock = $creator->createPageCardsBlock($page, 'sidebar', 2);
    $duplicateCardsBlock = $creator->createPageCardsBlock($page, 'sidebar', 2);

    expect($cardsBlock->assets()->count())->toBe(3)
        ->and($duplicateCardsBlock->assets()->count())->toBe(3)
        ->and(BlockAsset::query()->where('pageable_id', $page->getKey())->where('container', 'sidebar')->count())->toBe(3);
});

it('creates app showcase demo blocks with sections and media assets', function (): void {
    $language = Language::factory()->default()->create(['code' => 'en']);
    Site::factory()->language($language)->default()->withTranslations($language)->create();

    $creator = new DemoCreator;

    $hero = $creator->createApHeroBannerBlock();
    $cards = $creator->createApCardGridBlock();
    $features = $creator->createApFeatureListBlock();
    $genericFeatures = $creator->createFeatureListBlock();
    $cta = $creator->createApCtaSectionBlock();
    $gallery = $creator->createApImageGalleryBlock();

    expect($hero->key)->toBe('ap-hero-banner')
        ->and($cards->assets()->count())->toBe(3)
        ->and($features->assets()->count())->toBe(4)
        ->and($genericFeatures->assets()->count())->toBe(6)
        ->and($cta->translations()->count())->toBe(1)
        ->and($gallery->assets()->count())->toBe(6)
        ->and($creator->createApImageGalleryBlock()->assets()->count())->toBe(6);
});

it('creates modern demo blocks as repeatable section-backed blocks', function (): void {
    $language = Language::factory()->default()->create(['code' => 'en']);
    Site::factory()->language($language)->default()->withTranslations($language)->create();

    $creator = new DemoCreator;

    $featureList = $creator->createModernFeatureListBlock();
    $teamMembers = $creator->createModernTeamMembersBlock();
    $pricing = $creator->createModernPricingTableBlock();
    $testimonials = $creator->createModernTestimonialsBlock();
    $faq = $creator->createModernFaqBlock();
    $stats = $creator->createModernStatsSectionBlock();
    $alternating = $creator->createModernAlternatingContentBlock();
    $process = $creator->createModernProcessStepsBlock();
    $gallery = $creator->createModernImageGalleryBlock();

    expect($featureList->assets()->count())->toBe(6)
        ->and($teamMembers->assets()->count())->toBe(3)
        ->and($pricing->assets()->count())->toBe(3)
        ->and($testimonials->assets()->count())->toBe(3)
        ->and($faq->assets()->count())->toBe(4)
        ->and($stats->assets()->count())->toBe(4)
        ->and($alternating->assets()->count())->toBe(3)
        ->and($process->assets()->count())->toBe(4)
        ->and($gallery->assets()->count())->toBe(6)
        ->and($creator->createModernTeamMembersBlock()->assets()->count())->toBe(3);
});

it('creates the remaining standard section-backed demo blocks idempotently', function (): void {
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

    $faq = $creator->createFaqBlock($site->languages);
    $navigation = $creator->createStaticNavigationBlock($site->languages, $site);
    $contentsBlock = $creator->createFeatureListBlock();
    $creator->createContentsBlock($contentsBlock, $page, 'main', 3);
    $creator->createContentsBlock($contentsBlock, $page, 'main', 3);

    $businessFeatures = $creator->createBusinessFeaturesBlock($site);
    $banners = $creator->createBannersBlock();
    $testimonials = $creator->createTestimonialsBlock($site->languages);
    $teamPortfolio = $creator->createTeamPortfolioBlock($site->languages);

    expect($faq->assets()->count())->toBe(6)
        ->and($navigation->meta['navigation'])->toBe('example-menu')
        ->and($contentsBlock->assets()->where('pageable_id', $page->getKey())->where('container', 'main')->count())->toBe(4)
        ->and($businessFeatures->assets()->count())->toBe(7)
        ->and($banners->assets()->count())->toBe(7)
        ->and($testimonials->assets()->count())->toBe(3)
        ->and($teamPortfolio->assets()->count())->toBe(16)
        ->and(DemoAsset::query()->where('name', 'FAQs')->exists())->toBeTrue()
        ->and(DemoAsset::query()->where('name', 'Team Members')->exists())->toBeTrue();
});

function bindDemoBlockCreatorTinyResources(): void
{
    $demoDirectory = sys_get_temp_dir() . '/capell-demo-block-creator-resources-' . uniqid();
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
