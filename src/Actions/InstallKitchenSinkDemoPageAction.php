<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Actions\CreateDefaultLanguagesAction;
use Capell\Core\Actions\CreateThemeAction;
use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Enums\PageTypeEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\Core\Support\Creator\PageCreator;
use Capell\LayoutBuilder\Actions\InstallLayoutBuilderBlockCatalogAction;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Lorisleiva\Actions\Concerns\AsObject;

final class InstallKitchenSinkDemoPageAction
{
    use AsObject;

    private const string PageName = 'Kitchen Sink Demo Page';

    private const string PageSlug = 'kitchen-sink-demo';

    /**
     * @return array<int, string>
     */
    public static function sectionHeadings(): array
    {
        return [
            'Hero', 'Breadcrumbs', 'Table of contents', 'Paragraph styles', 'Heading hierarchy',
            'Blockquote / pull quote', 'Pre / code example', 'Unordered list', 'Ordered list',
            'Definition list', 'Callout / info box', 'Article card', 'Blog index list', 'News teaser',
            'Feature grid', 'Statistics strip', 'Testimonial block', 'Logo cloud', 'Pricing table/cards',
            'FAQ accordion', 'Tabs', 'Carousel/slider', 'Timeline', 'Process steps', 'Gallery',
            'Video embed', 'Audio player', 'Map embed', 'Table of data', 'Complex table',
            'Search results', 'Filter chips/tags', 'Form field demo', 'Full form', 'CTA band',
            'Alert variants', 'Embed block', 'Empty state', 'Error state', 'Footer',
        ];
    }

    public function handle(?Site $site = null): Page
    {
        $languages = $this->languages();
        InstallLayoutBuilderBlockCatalogAction::run($languages, extraBlocks: true);

        $site ??= $this->site($languages);
        $layout = $this->layout();
        $this->blocks($languages);

        /** @var Page $page */
        $page = resolve(PageCreator::class)->createPage([
            'name' => self::PageName,
            'layout_id' => $layout->getKey(),
            'type_key' => PageTypeEnum::Default,
            'visible_from' => now()->subDay()->format('Y-m-d'),
            'meta' => ['demo_fixture' => 'kitchen-sink'],
            'translations' => $this->pageTranslations($languages),
        ], $site, $languages);

        $this->syncPageAssets($page);
        SetupPageUrlsAction::run($page);

        return $page->refresh();
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

        return $site;
    }

    private function layout(): Layout
    {
        $blockKeys = array_keys($this->blockFamilies());

        /** @var Layout $layout */
        $layout = Layout::query()->updateOrCreate(
            ['key' => 'kitchen-sink-demo'],
            [
                'name' => self::PageName,
                'containers' => [
                    'main' => [
                        'meta' => ['landmark' => 'main'],
                        'widgets' => array_map(
                            static fn (string $key): array => ['widget_key' => $key, 'occurrence' => 1],
                            $blockKeys,
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
    private function blocks(EloquentCollection $languages): void
    {
        foreach ($this->blockFamilies() as $key => $family) {
            /** @var Widget|null $block */
            $block = Widget::query()->firstWhere('key', $key);

            if (! $block instanceof Widget) {
                continue;
            }

            $block->forceFill([
                'meta' => [
                    ...($block->meta ?? []),
                    'family' => $family['family'],
                    'sections' => $this->sections($family['headings']),
                ],
            ])->save();

            foreach ($languages as $language) {
                $block->translations()->updateOrCreate(
                    ['language_id' => $language->getKey()],
                    ['title' => $family['title'], 'content' => '<p>' . e($family['summary']) . '</p>'],
                );
            }
        }
    }

    private function syncPageAssets(Page $page): void
    {
        foreach (array_keys($this->blockFamilies()) as $order => $blockKey) {
            $block = Widget::query()->firstWhere('key', $blockKey);

            if (! $block instanceof Widget) {
                continue;
            }

            WidgetAsset::query()->updateOrCreate(
                [
                    'widget_id' => $block->getKey(),
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
     * @param  EloquentCollection<int, Language>  $languages
     * @return array<string, array<string, mixed>>
     */
    private function pageTranslations(EloquentCollection $languages): array
    {
        $translations = [];

        foreach ($languages as $language) {
            $translations[(string) $language->code] = [
                'title' => self::PageName,
                'content' => '<h1>Kitchen Sink Demo Page</h1><p>A CMS authoring and rendering reference fixture for Foundation Theme blocks.</p>',
                'summary' => 'A CMS reference page for testing Foundation Theme block rendering and accessibility.',
                'meta' => ['slug' => self::PageSlug, 'label' => self::PageName],
            ];
        }

        return $translations;
    }

    /**
     * @return array<string, array{family: string, title: string, summary: string, headings: array<int, string>}>
     */
    private function blockFamilies(): array
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
                'Content' => 'Render public copy from block translations, meta, or page-scoped assets.',
                'Variant rules' => 'Keep variants explicit in block data and avoid editor-only selectors.',
                'Behavior' => 'Prefer native controls, then enhance progressively.',
                'Accessibility' => 'Expose labels, captions, scoped headers, and clear text equivalents.',
            ],
        ], $headings);
    }
}
