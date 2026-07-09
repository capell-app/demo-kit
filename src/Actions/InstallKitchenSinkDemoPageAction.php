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
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use DateTimeInterface;
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

    private const string LivewireStressWidgetKey = 'kitchen-sink-livewire-stress';

    private const string LivewireLatestPagesWidgetKey = 'kitchen-sink-livewire-latest-pages';

    private const string HeroTopWidgetKey = 'kitchen-sink-hero-top';

    private const string HeroMiddleWidgetKey = 'kitchen-sink-hero-middle';

    private const string HeroDeepWidgetKey = 'kitchen-sink-hero-deep';

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
        $this->syncPageAssets($page);
        $this->syncPageSelectionAssets($page, $contextPages);
        $this->syncLivewireLatestPageAssets($page, $contextPages);
        SetupPageUrlsAction::run($page);
        $this->deleteLegacyNestedPageUrls($site);

        return $page->refresh();
    }

    /**
     * @return array<string, array{family: string, title: string, summary: string, headings: array<int, string>}>
     */
    private static function widgetFamilies(): array
    {
        return [
            'kitchen-sink-structured-text' => ['family' => 'Structured text', 'title' => 'Structured content reference', 'summary' => 'Hero, breadcrumbs, and table of contents patterns.', 'headings' => array_slice(self::sectionHeadings(), 0, 3)],
            'kitchen-sink-rich-text' => ['family' => 'Rich text', 'title' => 'Rich text reference', 'summary' => 'Text, hierarchy, quote, code, list, and callout patterns.', 'headings' => array_slice(self::sectionHeadings(), 3, 8)],
            'kitchen-sink-data-display' => ['family' => 'Data display', 'title' => 'Data display reference', 'summary' => 'Card, listing, teaser, feature, statistics, proof, logo, and pricing patterns.', 'headings' => array_slice(self::sectionHeadings(), 11, 8)],
            'kitchen-sink-interactions' => ['family' => 'Interactions', 'title' => 'Interaction reference', 'summary' => 'Accordion, tabs, carousel, timeline, and process behavior contracts.', 'headings' => array_slice(self::sectionHeadings(), 19, 5)],
            'kitchen-sink-embeds' => ['family' => 'Embeds', 'title' => 'Embeds reference', 'summary' => 'Gallery, media, map, and table contracts.', 'headings' => array_slice(self::sectionHeadings(), 24, 5)],
            'kitchen-sink-forms' => ['family' => 'Forms', 'title' => 'Forms reference', 'summary' => 'Complex table, search, filter, field, full-form, and CTA examples.', 'headings' => array_slice(self::sectionHeadings(), 29, 6)],
            'kitchen-sink-utility-states' => ['family' => 'Utility states', 'title' => 'Utility states reference', 'summary' => 'Alert, embed, empty, error, and footer state contracts.', 'headings' => array_slice(self::sectionHeadings(), 35, 5)],
        ];
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
        $this->configureCatalogWidgets();
        CreateKitchenSinkSourceWidgetsAction::run($languages);

        foreach (self::widgetFamilies() as $key => $family) {
            /** @var Widget|null $widget */
            $widget = Widget::query()->firstWhere('key', $key);

            if (! $widget instanceof Widget) {
                continue;
            }

            $widget->forceFill([
                'meta' => [
                    ...($widget->meta ?? []),
                    'family' => $family['family'],
                    'sections' => $this->sections($family['headings']),
                ],
            ])->save();

            foreach ($languages as $language) {
                $widget->translations()->updateOrCreate(
                    ['language_id' => $language->getKey()],
                    ['title' => $family['title'], 'content' => '<p>' . e($family['summary']) . '</p>'],
                );
            }
        }

        CreateKitchenSinkVariantWidgetsAction::run($languages, self::layoutWidgetEntries());
    }

    private function configureCatalogWidgets(): void
    {
        foreach (['latest-pages', 'pages-card'] as $widgetKey) {
            $widget = Widget::query()->firstWhere('key', $widgetKey);

            if (! $widget instanceof Widget) {
                continue;
            }

            $widget->forceFill([
                'meta' => [
                    ...($widget->meta ?? []),
                    'limit' => 2,
                    'pagination' => true,
                ],
            ])->save();
        }
    }

    private function syncPageAssets(Page $page): void
    {
        WidgetAsset::query()
            ->where('pageable_type', $page->getMorphClass())
            ->where('pageable_id', $page->getKey())
            ->delete();

        $rows = [];
        $timestamp = now();

        foreach (self::layoutWidgetEntries() as $order => $entry) {
            if ($entry['source_key'] === self::LivewireLatestPagesWidgetKey) {
                continue;
            }

            $widget = Widget::query()->firstWhere('key', $entry['widget_key']);

            if (! $widget instanceof Widget) {
                continue;
            }

            $rows[] = $this->widgetAssetRow(
                page: $page,
                widget: $widget,
                asset: $page,
                order: $order + 1,
                meta: [
                    'scope' => 'kitchen-sink-demo',
                    'caption' => sprintf('Primary page asset for stress widget %03d', $order + 1),
                    'role' => 'primary-page',
                    'accent' => ['teal', 'blue', 'slate', 'amber'][$order % 4],
                ],
                timestamp: $timestamp,
            );
        }

        $this->insertWidgetAssetRows($rows);
    }

    /**
     * @param  array<int, Page>  $pages
     */
    private function syncPageSelectionAssets(Page $page, array $pages): void
    {
        $pages = array_slice($pages, 0, self::contextAssetLimit());
        $assetWidgetKeys = collect(self::layoutWidgetEntries())
            ->filter(fn (array $entry): bool => in_array($entry['source_key'], self::PageAssetWidgetKeys, true))
            ->pluck('widget_key')
            ->values();

        $rows = [];
        $timestamp = now();

        foreach ($assetWidgetKeys as $widgetKey) {
            $widget = Widget::query()->firstWhere('key', $widgetKey);

            if (! $widget instanceof Widget) {
                continue;
            }

            foreach ($pages as $order => $assetPage) {
                $rows[] = $this->widgetAssetRow(
                    page: $page,
                    widget: $widget,
                    asset: $assetPage,
                    order: $order + 1,
                    meta: [
                        'scope' => 'kitchen-sink-demo-page-selection',
                        'caption' => $assetPage->translation?->title ?? $assetPage->name,
                        'content' => $assetPage->translation?->summary ?? $assetPage->name,
                        'role' => 'selected-page',
                        'accent' => ['teal', 'blue', 'slate', 'amber'][$order % 4],
                        'crop_preset' => ['thumbnail', 'card', 'hero'][$order % 3],
                    ],
                    timestamp: $timestamp,
                );
            }
        }

        $this->insertWidgetAssetRows($rows);
    }

    /**
     * @param  array<int, Page>  $pages
     */
    private function syncLivewireLatestPageAssets(Page $page, array $pages): void
    {
        $widgetKeys = collect(self::layoutWidgetEntries())
            ->filter(fn (array $entry): bool => $entry['source_key'] === self::LivewireLatestPagesWidgetKey)
            ->pluck('widget_key')
            ->values();

        $rows = [];
        $timestamp = now();

        foreach ($widgetKeys as $widgetKey) {
            $widget = Widget::query()->firstWhere('key', $widgetKey);

            if (! $widget instanceof Widget) {
                continue;
            }

            foreach (array_slice($pages, 0, self::contextAssetLimit()) as $order => $assetPage) {
                $rows[] = $this->widgetAssetRow(
                    page: $page,
                    widget: $widget,
                    asset: $assetPage,
                    order: $order + 1,
                    meta: [
                        'scope' => 'kitchen-sink-livewire-latest-pages',
                        'caption' => $assetPage->translation?->title ?? $assetPage->name,
                        'content' => $assetPage->translation?->summary ?? $assetPage->name,
                        'role' => 'selected-page',
                    ],
                    timestamp: $timestamp,
                );
            }
        }

        $this->insertWidgetAssetRows($rows);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function widgetAssetRow(Page $page, Widget $widget, Page $asset, int $order, array $meta, DateTimeInterface $timestamp): array
    {
        return [
            'workspace_id' => 0,
            'widget_id' => $widget->getKey(),
            'pageable_type' => $page->getMorphClass(),
            'pageable_id' => $page->getKey(),
            'container' => 'main',
            'occurrence' => 1,
            'asset_type' => $asset->getMorphClass(),
            'asset_id' => $asset->getKey(),
            'order' => $order,
            'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertWidgetAssetRows(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            WidgetAsset::query()->insert($chunk);
        }
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

    private function imageNameForIndex(int $index): string
    {
        $images = [
            'pricing',
            'fresh-water',
            'salt-water',
            'birds',
            'fish',
            'reptiles',
            'mammals',
            'cats',
            'dogs',
            'sharks',
            'owls',
            'eagles',
        ];

        return $images[($index - 1) % count($images)];
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

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @return array<string, array<string, mixed>>
     */
    private function simplePageTranslations(EloquentCollection $languages, string $title, string $slug, string $summary): array
    {
        $translations = [];

        foreach ($languages as $language) {
            $translations[(string) $language->code] = [
                'title' => $title,
                'content' => '<p>' . e($summary) . '</p>',
                'summary' => $summary,
                'meta' => [
                    'slug' => $slug,
                    'label' => $title,
                    'exclude_from_footer' => true,
                    'demo_fixture' => 'kitchen-sink-context',
                ],
            ];
        }

        return $translations;
    }

    /**
     * @param  array<int, string>  $headings
     * @return array<int, array<string, mixed>>
     */
    private function sections(array $headings): array
    {
        return array_map(static fn (string $heading): array => [
            'key' => str($heading)->lower()->replace([' / ', '/', ' '], ['-', '-', '-'])->replaceMatches('/[^a-z0-9-]/', '')->toString(),
            'heading' => $heading,
            'summary' => sprintf('%s reference section.', $heading),
            'notes' => [
                'Purpose' => sprintf('Shows how %s should render in Foundation Theme.', strtolower($heading)),
                'Layout' => 'Use semantic grouping and predictable heading order.',
                'Content' => 'Render public copy from widget translations, meta, or page-scoped assets.',
                'Variant rules' => 'Keep variants explicit in widget data and avoid internal selectors.',
                'Behavior' => 'Prefer native controls, then enhance progressively.',
                'Accessibility' => 'Expose labels, captions, scoped headers, and clear text equivalents.',
            ],
        ], $headings);
    }
}
