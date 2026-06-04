<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Actions\CreateDefaultLanguagesAction;
use Capell\Core\Actions\CreateThemeAction;
use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Enums\PageTypeEnum;
use Capell\Core\Enums\PresentationDeliveryMode;
use Capell\Core\Enums\PresentationLoadingStrategy;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\Core\Support\Creator\PageCreator;
use Capell\LayoutBuilder\Actions\InstallLayoutBuilderWidgetCatalogAction;
use Capell\LayoutBuilder\Data\WidgetDefinitionData;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Lorisleiva\Actions\Concerns\AsObject;

final class InstallKitchenSinkDemoPageAction
{
    use AsObject;

    private const string PageName = 'Kitchen Sink Demo Page';

    private const string PageSlug = 'kitchen-sink-demo';

    private const string ParentPageName = 'Kitchen Sink Showcase';

    private const string ParentPageSlug = 'kitchen-sink-showcase';

    /**
     * @var array<int, string>
     */
    private const array LazyWidgetKeys = [
        'kitchen-sink-rich-text',
        'kitchen-sink-data-display',
        'kitchen-sink-interactions',
        'kitchen-sink-embeds',
        'kitchen-sink-forms',
        'kitchen-sink-utility-states',
    ];

    /**
     * @var array<int, string>
     */
    private const array PageAssetWidgetKeys = [
        'pages-card',
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
        $referenceWidgetKeys = array_keys(self::widgetFamilies());
        $definitions = [
            ...WidgetDefinitionData::defaultCatalog(),
            ...WidgetDefinitionData::extraCatalog(),
        ];

        $catalogWidgetKeys = collect($definitions)
            ->map(static fn (WidgetDefinitionData $definition): string => $definition->key)
            ->reject(static fn (string $key): bool => in_array($key, $referenceWidgetKeys, true))
            ->unique()
            ->values()
            ->all();

        return [
            ...$catalogWidgetKeys,
            ...$referenceWidgetKeys,
        ];
    }

    public function handle(?Site $site = null): Page
    {
        $languages = $this->languages();
        InstallLayoutBuilderWidgetCatalogAction::run($languages, extraWidgets: true);

        $site ??= $this->site($languages);
        $layout = $this->layout();
        $this->widgets($languages);
        $parentPage = $this->parentPage($site, $layout, $languages);

        $this->adoptExistingKitchenSinkPage($site, $layout, $parentPage);

        /** @var Page $page */
        $page = resolve(PageCreator::class)->createPage([
            'name' => self::PageName,
            'layout_id' => $layout->getKey(),
            'type_key' => PageTypeEnum::Default,
            'parent_id' => $parentPage->getKey(),
            'visible_from' => now()->subDay()->format('Y-m-d'),
            'meta' => ['demo_fixture' => 'kitchen-sink'],
            'translations' => $this->pageTranslations($languages),
        ], $site, $languages);

        $page->forceFill(['order' => 20])->save();

        $contextPages = $this->contextPages($site, $layout, $languages, $parentPage, $page);
        $this->syncPageAssets($page);
        $this->syncPageSelectionAssets($page, $contextPages);
        SetupPageUrlsAction::run($page);

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
    private function languages(): EloquentCollection
    {
        return CreateDefaultLanguagesAction::run(['en']);
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     */
    private function site(EloquentCollection $languages): Site
    {
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

        return $site;
    }

    private function layout(): Layout
    {
        $widgetKeys = self::layoutWidgetKeys();

        /** @var Layout $layout */
        $layout = Layout::query()->updateOrCreate(
            ['key' => 'kitchen-sink-demo'],
            [
                'name' => self::PageName,
                'containers' => [
                    'main' => [
                        'meta' => ['landmark' => 'main'],
                        'widgets' => array_map(
                            fn (string $key): array => in_array($key, self::LazyWidgetKeys, true)
                                ? $this->lazyWidget($key)
                                : $this->eagerWidget($key),
                            $widgetKeys,
                        ),
                    ],
                ],
                'status' => true,
            ],
        );

        return $layout;
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     */
    private function parentPage(Site $site, Layout $layout, EloquentCollection $languages): Page
    {
        /** @var Page $page */
        $page = resolve(PageCreator::class)->createPage([
            'name' => self::ParentPageName,
            'layout_id' => $layout->getKey(),
            'type_key' => PageTypeEnum::Default,
            'visible_from' => now()->subDay()->format('Y-m-d'),
            'meta' => ['demo_fixture' => 'kitchen-sink-parent'],
            'translations' => $this->simplePageTranslations(
                languages: $languages,
                title: self::ParentPageName,
                slug: self::ParentPageSlug,
                summary: 'A parent page that gives the Kitchen Sink fixture a real page hierarchy.',
            ),
        ], $site, $languages);

        $page->forceFill(['order' => 10])->save();
        SetupPageUrlsAction::run($page);

        return $page->refresh();
    }

    private function adoptExistingKitchenSinkPage(Site $site, Layout $layout, Page $parentPage): void
    {
        $page = Page::query()
            ->where('site_id', $site->getKey())
            ->where('layout_id', $layout->getKey())
            ->where('name', self::PageName)
            ->first();

        if (! $page instanceof Page || (int) $page->parent_id === (int) $parentPage->getKey()) {
            return;
        }

        $page->forceFill(['parent_id' => $parentPage->getKey()])->save();
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @return array<int, Page>
     */
    private function contextPages(Site $site, Layout $layout, EloquentCollection $languages, Page $parentPage, Page $page): array
    {
        $pages = [
            ...$this->siblingPages($site, $layout, $languages, $parentPage),
            ...$this->childPages($site, $layout, $languages, $page),
        ];

        foreach ($pages as $contextPage) {
            SetupPageUrlsAction::run($contextPage);
        }

        return $pages;
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @return array<int, Page>
     */
    private function siblingPages(Site $site, Layout $layout, EloquentCollection $languages, Page $parentPage): array
    {
        return collect([
            ['name' => 'Kitchen Sink Content Patterns', 'slug' => 'kitchen-sink-content-patterns', 'summary' => 'A sibling page for content pattern widgets.', 'order' => 30],
            ['name' => 'Kitchen Sink Interaction Patterns', 'slug' => 'kitchen-sink-interaction-patterns', 'summary' => 'A sibling page for interactive widget patterns.', 'order' => 40],
            ['name' => 'Kitchen Sink Media Patterns', 'slug' => 'kitchen-sink-media-patterns', 'summary' => 'A sibling page for media and embed widget patterns.', 'order' => 50],
        ])
            ->map(fn (array $data): Page => $this->contextPage($site, $layout, $languages, $parentPage, $data))
            ->all();
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @return array<int, Page>
     */
    private function childPages(Site $site, Layout $layout, EloquentCollection $languages, Page $page): array
    {
        return collect([
            ['name' => 'Kitchen Sink Child Overview', 'slug' => 'kitchen-sink-child-overview', 'summary' => 'A child page for hierarchy-aware widgets.', 'order' => 10],
            ['name' => 'Kitchen Sink Child Detail', 'slug' => 'kitchen-sink-child-detail', 'summary' => 'A child page for selected page-card widgets.', 'order' => 20],
            ['name' => 'Kitchen Sink Child Reference', 'slug' => 'kitchen-sink-child-reference', 'summary' => 'A child page for related asset widgets.', 'order' => 30],
        ])
            ->map(fn (array $data): Page => $this->contextPage($site, $layout, $languages, $page, $data))
            ->all();
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @param  array{name: string, slug: string, summary: string, order: int}  $data
     */
    private function contextPage(Site $site, Layout $layout, EloquentCollection $languages, Page $parentPage, array $data): Page
    {
        /** @var Page $page */
        $page = resolve(PageCreator::class)->createPage([
            'name' => $data['name'],
            'layout_id' => $layout->getKey(),
            'type_key' => PageTypeEnum::Default,
            'parent_id' => $parentPage->getKey(),
            'visible_from' => now()->subDay()->format('Y-m-d'),
            'meta' => ['demo_fixture' => 'kitchen-sink-context'],
            'translations' => $this->simplePageTranslations(
                languages: $languages,
                title: $data['name'],
                slug: $data['slug'],
                summary: $data['summary'],
            ),
        ], $site, $languages);

        $page->forceFill(['order' => $data['order']])->save();

        return $page->refresh();
    }

    /**
     * @return array{widget_key: string, occurrence: int}
     */
    private function eagerWidget(string $key): array
    {
        return ['widget_key' => $key, 'occurrence' => 1];
    }

    /**
     * @return array{widget_key: string, occurrence: int, meta: array{presentation: array{delivery_mode: string, loading_strategy: string}}}
     */
    private function lazyWidget(string $key): array
    {
        return [
            'widget_key' => $key,
            'occurrence' => 1,
            'meta' => [
                'presentation' => $this->lazyPresentation(),
            ],
        ];
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
        foreach (array_keys(self::widgetFamilies()) as $order => $widgetKey) {
            $widget = Widget::query()->firstWhere('key', $widgetKey);

            if (! $widget instanceof Widget) {
                continue;
            }

            WidgetAsset::query()->updateOrCreate(
                [
                    'widget_id' => $widget->getKey(),
                    'pageable_type' => $page->getMorphClass(),
                    'pageable_id' => $page->getKey(),
                    'container' => 'main',
                    'occurrence' => 1,
                    'asset_type' => $page->getMorphClass(),
                    'asset_id' => $page->getKey(),
                ],
                ['order' => $order + 1, 'meta' => ['scope' => 'kitchen-sink-demo']],
            );
        }
    }

    /**
     * @param  array<int, Page>  $pages
     */
    private function syncPageSelectionAssets(Page $page, array $pages): void
    {
        foreach (self::PageAssetWidgetKeys as $widgetKey) {
            $widget = Widget::query()->firstWhere('key', $widgetKey);

            if (! $widget instanceof Widget) {
                continue;
            }

            foreach ($pages as $order => $assetPage) {
                WidgetAsset::query()->updateOrCreate(
                    [
                        'widget_id' => $widget->getKey(),
                        'pageable_type' => $page->getMorphClass(),
                        'pageable_id' => $page->getKey(),
                        'container' => 'main',
                        'occurrence' => 1,
                        'asset_type' => $assetPage->getMorphClass(),
                        'asset_id' => $assetPage->getKey(),
                    ],
                    ['order' => $order + 1, 'meta' => ['scope' => 'kitchen-sink-demo-page-selection']],
                );
            }
        }
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
                'content' => '<h1>Kitchen Sink Demo Page</h1><p>A CMS authoring and rendering reference fixture for Foundation Theme widgets.</p>',
                'summary' => 'A CMS reference page for testing Foundation Theme widget rendering and accessibility.',
                'meta' => ['slug' => self::PageSlug, 'label' => self::PageName],
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
                'meta' => ['slug' => $slug, 'label' => $title],
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
                'Variant rules' => 'Keep variants explicit in widget data and avoid editor-only selectors.',
                'Behavior' => 'Prefer native controls, then enhance progressively.',
                'Accessibility' => 'Expose labels, captions, scoped headers, and clear text equivalents.',
            ],
        ], $headings);
    }
}
