<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Actions\CreateDefaultLanguagesAction;
use Capell\Core\Actions\CreateThemeAction;
use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Enums\PageTypeEnum;
use Capell\Core\Enums\PresentationDeliveryMode;
use Capell\Core\Enums\PresentationLoadingStrategy;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\Core\Support\Creator\PageCreator;
use Capell\LayoutBuilder\Actions\InstallLayoutBuilderWidgetCatalogAction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\MediaLibrary\HasMedia;

/**
 * @method static Page run(?Site $site = null)
 */
final class InstallKitchenSinkDemoPageAction
{
    use AsObject;

    private const string PageName = 'Kitchen Sink Demo Page';

    private const string PageSlug = 'kitchen-sink-showcase';

    private const string LegacyParentPageName = 'Kitchen Sink Showcase';

    private const int DefaultEagerWidgetLimit = 20;

    private const int DefaultTargetWidgetCount = 120;

    private const int DefaultContextPageCount = 24;

    private const int DefaultContextAssetLimit = 12;

    private const string LivewireLatestPagesWidgetKey = 'kitchen-sink-livewire-latest-pages';

    /**
     * @var array<int, string>
     */
    private const array PageAssetWidgetKeys = [
        'assets',
        'assets-accordion',
        'assets-banner',
        'assets-widget',
        'asset-features',
        'asset-testimonials',
        'pages-card',
        'gallery',
        'media-carousel',
        'ap-card-grid',
        'ap-feature-list',
        'ap-image-gallery',
        'ap-team-members',
        'ap-pricing-table',
        'ap-testimonials',
        'ap-faq-section',
        'ap-stats-section',
        'ap-alternating-content',
        'ap-process-steps',
    ];

    /**
     * @return array<int, string>
     */
    public static function sectionHeadings(): array
    {
        return [
            'Hero', 'Breadcrumbs', 'Table of contents', 'Paragraph styles', 'Heading hierarchy',
            'Widgetquote / pull quote', 'Pre / code example', 'Unordered list', 'Ordered list',
            'Definition list', 'Callout / info box', 'Article card', 'Blog index list', 'News teaser',
            'Feature grid', 'Statistics strip', 'Testimonial widget', 'Logo cloud', 'Pricing table/cards',
            'FAQ accordion', 'Tabs', 'Carousel/slider', 'Timeline', 'Process steps', 'Gallery',
            'Video embed', 'Audio player', 'Map embed', 'Table of data', 'Complex table',
            'Search results', 'Filter chips/tags', 'Form field demo', 'Full form', 'CTA band',
            'Alert variants', 'Embed widget', 'Empty state', 'Error state', 'Footer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function layoutWidgetKeys(): array
    {
        return collect(self::layoutWidgetEntries())
            ->pluck('widget_key')
            ->all();
    }

    public static function targetWidgetCount(): int
    {
        return max(1, (int) config('capell-demo-kit.kitchen_sink.target_widget_count', self::DefaultTargetWidgetCount));
    }

    public static function eagerWidgetLimit(): int
    {
        return max(1, (int) config('capell-demo-kit.kitchen_sink.eager_widget_limit', self::DefaultEagerWidgetLimit));
    }

    public static function contextPageCount(): int
    {
        return max(1, (int) config('capell-demo-kit.kitchen_sink.context_page_count', self::DefaultContextPageCount));
    }

    public static function contextAssetLimit(): int
    {
        return max(1, (int) config('capell-demo-kit.kitchen_sink.context_asset_limit', self::DefaultContextAssetLimit));
    }

    /**
     * @return array<int, array{widget_key: string, source_key: string, occurrence: int, stress_index: int, variant: string, lazy: bool}>
     */
    public static function layoutWidgetEntries(): array
    {
        return BuildKitchenSinkLayoutWidgetEntriesAction::run(
            self::targetWidgetCount(),
            self::eagerWidgetLimit(),
        );
    }

    public function handle(?Site $site = null): Page
    {
        $site ??= $this->site();
        $languages = $this->languages($site);
        InstallLayoutBuilderWidgetCatalogAction::run($languages, extraWidgets: true);

        $this->ensureSiteDomains($site, $languages);

        $layout = $this->layout();
        $this->widgets($languages);

        $this->adoptLegacyKitchenSinkShowcasePage($site, $layout);

        /** @var Page $page */
        $page = resolve(PageCreator::class)->createPage([
            'name' => self::PageName,
            'layout_id' => $layout->getKey(),
            'type_key' => PageTypeEnum::Default,
            'visible_from' => now()->subDay()->format('Y-m-d'),
            'meta' => ['demo_fixture' => 'kitchen-sink'],
            'translations' => $this->pageTranslations($languages),
        ], $site, $languages);

        $page->forceFill(['order' => 20])->save();
        $this->ensureDemoMedia($page, 'pricing', MediaCollectionEnum::Image);

        $contextPages = CreateKitchenSinkContextPagesAction::run(
            $site,
            $layout,
            $languages,
            $page,
            self::contextPageCount(),
        );
        SyncKitchenSinkPageAssetsAction::run(
            $page,
            $contextPages,
            array_values(self::layoutWidgetEntries()),
            self::PageAssetWidgetKeys,
            self::LivewireLatestPagesWidgetKey,
            self::contextAssetLimit(),
        );
        SetupPageUrlsAction::run($page);
        $this->deleteLegacyNestedPageUrls($site);

        return $page->refresh();
    }

    /**
     * @return EloquentCollection<int, Language>
     */
    private function languages(Site $site): EloquentCollection
    {
        $site->loadMissing(['language', 'languages']);

        /** @var EloquentCollection<int, Language> $languages */
        $languages = $site->languages instanceof EloquentCollection
            ? $site->languages
            : new EloquentCollection;

        if ($site->language instanceof Language && $languages->doesntContain('id', $site->language->getKey())) {
            $languages->prepend($site->language);
        }

        if ($languages->isNotEmpty()) {
            return $languages->unique('id')->values();
        }

        if ($site->language_id !== null) {
            $language = Language::query()->find($site->language_id);

            if ($language instanceof Language) {
                return new EloquentCollection([$language]);
            }
        }

        return CreateDefaultLanguagesAction::run(['en']);
    }

    private function site(): Site
    {
        $existingSite = $this->preferredDemoSite()
            ?? Site::query()
                ->with(['language', 'languages', 'siteDomains'])
                ->default()
                ->first()
            ?? Site::query()
                ->with(['language', 'languages', 'siteDomains'])
                ->orderBy('id')
                ->first();

        if ($existingSite instanceof Site) {
            return $existingSite;
        }

        $languages = CreateDefaultLanguagesAction::run(['en']);
        $siteType = resolve(BlueprintCreator::class)->createSiteType();
        $language = $languages->first();

        /** @var Site $site */
        $site = Site::query()->firstOrCreate(
            ['name' => 'Kitchen Sink Demo'],
            [
                'blueprint_id' => $siteType->getKey(),
                'language_id' => $language?->getKey(),
                'theme_id' => CreateThemeAction::run(key: 'default', name: 'Foundation')->getKey(),
                'status' => true,
                'default' => ! Site::query()->default()->exists(),
            ],
        );

        return $site;
    }

    private function preferredDemoSite(): ?Site
    {
        /** @var Site|null $site */
        $site = Site::query()
            ->with(['language', 'languages', 'siteDomains'])
            ->where('name', 'Capell Services')
            ->first();

        return $site;
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     */
    private function ensureSiteDomains(Site $site, EloquentCollection $languages): void
    {
        foreach ($languages as $siteLanguage) {
            SiteDomain::query()->firstOrCreate([
                'site_id' => $site->getKey(),
                'language_id' => $siteLanguage->getKey(),
            ], [
                'domain' => null,
                'scheme' => null,
                'path' => null,
                'default' => ! SiteDomain::query()->where('site_id', $site->getKey())->exists(),
                'status' => true,
            ]);
        }
    }

    private function layout(): Layout
    {
        /** @var Layout $layout */
        $layout = Layout::query()->updateOrCreate(
            ['key' => 'kitchen-sink-demo'],
            [
                'name' => self::PageName,
                'containers' => [
                    'main' => [
                        'meta' => ['landmark' => 'main'],
                        'widgets' => array_map($this->layoutWidget(...), self::layoutWidgetEntries()),
                    ],
                ],
                'status' => true,
            ],
        );

        return $layout;
    }

    private function adoptLegacyKitchenSinkShowcasePage(Site $site, Layout $layout): void
    {
        $legacyParentPage = Page::query()
            ->where('site_id', $site->getKey())
            ->where('layout_id', $layout->getKey())
            ->where('name', self::LegacyParentPageName)
            ->where('meta->demo_fixture', 'kitchen-sink-parent')
            ->first();

        $legacyPage = Page::query()
            ->where('site_id', $site->getKey())
            ->where('layout_id', $layout->getKey())
            ->where('name', self::PageName)
            ->first();

        if ($legacyParentPage instanceof Page) {
            if ($legacyPage instanceof Page) {
                Page::query()
                    ->where('parent_id', $legacyPage->getKey())
                    ->update(['parent_id' => $legacyParentPage->getKey()]);

                $legacyPage->pageUrls()->withTrashed()->delete();
                $legacyPage->delete();
            }

            $legacyParentPage->forceFill([
                'name' => self::PageName,
                'meta' => ['demo_fixture' => 'kitchen-sink'],
                'order' => 20,
            ])->save();

            return;
        }

        if (! $legacyPage instanceof Page || $legacyPage->parent_id === null) {
            return;
        }

        $legacyPage->forceFill(['parent_id' => null])->save();
    }

    private function deleteLegacyNestedPageUrls(Site $site): void
    {
        PageUrl::query()
            ->where('site_id', $site->getKey())
            ->where('url', '/' . self::PageSlug . '/kitchen-sink-demo')
            ->delete();
    }

    /**
     * @param  array{widget_key: string, source_key: string, occurrence: int, stress_index: int, variant: string, lazy: bool}  $entry
     * @return array{widget_key: string, occurrence: int, meta: array<string, mixed>}
     */
    private function layoutWidget(array $entry): array
    {
        $widget = [
            'widget_key' => $entry['widget_key'],
            'occurrence' => 1,
            'meta' => [
                'kitchen_sink' => [
                    'source_key' => $entry['source_key'],
                    'stress_index' => $entry['stress_index'],
                    'variant' => $entry['variant'],
                ],
            ],
        ];

        if ($entry['lazy']) {
            $widget['meta']['presentation'] = $this->lazyPresentation();
        }

        return $widget;
    }

    /**
     * @return array{delivery_mode: string, loading_strategy: string}
     */
    private function lazyPresentation(): array
    {
        return [
            'delivery_mode' => PresentationDeliveryMode::LazyFragment->value,
            'loading_strategy' => PresentationLoadingStrategy::Visible->value,
        ];
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     */
    private function widgets(EloquentCollection $languages): void
    {
        ConfigureKitchenSinkReferenceWidgetsAction::run($languages);
        CreateKitchenSinkSourceWidgetsAction::run($languages);

        CreateKitchenSinkVariantWidgetsAction::run($languages, array_values(self::layoutWidgetEntries()));
    }

    private function ensureDemoMedia(Model $model, string $name, MediaCollectionEnum|string $collection): void
    {
        if (! $model instanceof HasMedia) {
            return;
        }

        $collectionName = $collection instanceof MediaCollectionEnum ? $collection->value : $collection;

        if ($model->getMedia($collectionName)->isNotEmpty()) {
            return;
        }

        $path = $this->demoImagePath($name);

        if ($path === null) {
            return;
        }

        $model->addMedia($path)
            ->preservingOriginal()
            ->toMediaCollection($collectionName);
    }

    private function demoImagePath(string $name): ?string
    {
        $imageDirectory = realpath(__DIR__ . '/../../demo/img');

        if ($imageDirectory === false) {
            return null;
        }

        $preferred = $imageDirectory . '/' . Str::slug($name) . '.jpg';

        if (File::exists($preferred)) {
            return $preferred;
        }

        $fallbacks = glob($imageDirectory . '/*.jpg');

        if ($fallbacks === false || $fallbacks === []) {
            return null;
        }

        return $fallbacks[crc32($name) % count($fallbacks)] ?? null;
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @return array<string, array<string, mixed>>
     */
    private function pageTranslations(EloquentCollection $languages): array
    {
        $translations = [];

        foreach ($languages as $language) {
            $translations[(string) $language->code] = [
                'title' => self::PageName,
                'content' => sprintf(
                    '<h1>Kitchen Sink Demo Page</h1><p>A %d-widget stress fixture covering Capell layout rendering, media assets, Livewire widgets, page lists, lazy fragments, reusable content, and accessibility edge cases.</p>',
                    self::targetWidgetCount(),
                ),
                'summary' => 'A CMS stress fixture for testing Capell widget rendering, lazy loading, media, and accessibility.',
                'meta' => [
                    'slug' => self::PageSlug,
                    'label' => self::PageName,
                    'exclude_from_footer' => true,
                    'demo_fixture' => 'kitchen-sink',
                ],
            ];
        }

        return $translations;
    }
}
