<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Enums\PageTypeEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\PageCreator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\MediaLibrary\HasMedia;

/**
 * @method static list<Page> run(Site $site, Layout $layout, EloquentCollection<int, Language> $languages, Page $parentPage, int $limit)
 */
final class CreateKitchenSinkContextPagesAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @return list<Page>
     */
    public function handle(Site $site, Layout $layout, EloquentCollection $languages, Page $parentPage, int $limit): array
    {
        $pages = array_values(collect(array_slice($this->pageDefinitions(), 0, max(1, $limit)))
            ->map(fn (array $data): Page => $this->createPage($site, $layout, $languages, $parentPage, $data))
            ->all());

        foreach ($pages as $index => $page) {
            $this->ensureDemoMedia($page, $this->imageNameForIndex($index + 1));
            SetupPageUrlsAction::run($page);
        }

        return $pages;
    }

    /** @return list<array{name: string, slug: string, summary: string, order: int}> */
    private function pageDefinitions(): array
    {
        return [
            ['name' => 'Kitchen Sink Content Patterns', 'slug' => 'kitchen-sink-content-patterns', 'summary' => 'Content, rich text, callout, and editorial widget patterns.', 'order' => 30],
            ['name' => 'Kitchen Sink Interaction Patterns', 'slug' => 'kitchen-sink-interaction-patterns', 'summary' => 'Accordion, tab, carousel, pagination, and Livewire interaction patterns.', 'order' => 40],
            ['name' => 'Kitchen Sink Media Patterns', 'slug' => 'kitchen-sink-media-patterns', 'summary' => 'Image, gallery, carousel, video, and responsive media patterns.', 'order' => 50],
            ['name' => 'Kitchen Sink Commerce Patterns', 'slug' => 'kitchen-sink-commerce-patterns', 'summary' => 'Pricing, CTA, proof, and conversion composition patterns.', 'order' => 60],
            ['name' => 'Kitchen Sink Data Patterns', 'slug' => 'kitchen-sink-data-patterns', 'summary' => 'Tables, stats, cards, index lists, and structured data patterns.', 'order' => 70],
            ['name' => 'Kitchen Sink Empty States', 'slug' => 'kitchen-sink-empty-states', 'summary' => 'Empty, loading, error, and fallback rendering patterns.', 'order' => 80],
            ['name' => 'Kitchen Sink Accessibility Patterns', 'slug' => 'kitchen-sink-accessibility-patterns', 'summary' => 'Keyboard, focus, label, landmark, and contrast stress patterns.', 'order' => 90],
            ['name' => 'Kitchen Sink Theme Patterns', 'slug' => 'kitchen-sink-theme-patterns', 'summary' => 'Theme token, background, spacing, and dark mode stress patterns.', 'order' => 100],
            ['name' => 'Kitchen Sink Navigation Patterns', 'slug' => 'kitchen-sink-navigation-patterns', 'summary' => 'Breadcrumbs, tabs, navigation widgets, and page relation patterns.', 'order' => 110],
            ['name' => 'Kitchen Sink Lazy Patterns', 'slug' => 'kitchen-sink-lazy-patterns', 'summary' => 'Lazy fragment, below-fold, and deferred payload stress patterns.', 'order' => 120],
            ['name' => 'Kitchen Sink Long Copy Patterns', 'slug' => 'kitchen-sink-long-copy-patterns', 'summary' => 'Long labels, long words, and dense copy fitting patterns.', 'order' => 130],
            ['name' => 'Kitchen Sink Builder Patterns', 'slug' => 'kitchen-sink-builder-patterns', 'summary' => 'Layout composition, repeated widgets, and rendering stress patterns.', 'order' => 140],
            ['name' => 'Kitchen Sink Child Overview', 'slug' => 'kitchen-sink-child-overview', 'summary' => 'A child page for hierarchy-aware widgets.', 'order' => 150],
            ['name' => 'Kitchen Sink Child Detail', 'slug' => 'kitchen-sink-child-detail', 'summary' => 'A child page for selected page-card widgets.', 'order' => 160],
            ['name' => 'Kitchen Sink Child Reference', 'slug' => 'kitchen-sink-child-reference', 'summary' => 'A child page for related asset widgets.', 'order' => 170],
            ['name' => 'Kitchen Sink Child Media', 'slug' => 'kitchen-sink-child-media', 'summary' => 'A child page for image-backed card and gallery widgets.', 'order' => 180],
            ['name' => 'Kitchen Sink Child Forms', 'slug' => 'kitchen-sink-child-forms', 'summary' => 'A child page for form and validation examples.', 'order' => 190],
            ['name' => 'Kitchen Sink Child Livewire', 'slug' => 'kitchen-sink-child-livewire', 'summary' => 'A child page for Livewire island and interaction examples.', 'order' => 200],
            ['name' => 'Kitchen Sink Child Dense Tables', 'slug' => 'kitchen-sink-child-dense-tables', 'summary' => 'A child page for dense table and scrolling content examples.', 'order' => 210],
            ['name' => 'Kitchen Sink Child CTA', 'slug' => 'kitchen-sink-child-cta', 'summary' => 'A child page for CTA and conversion block examples.', 'order' => 220],
            ['name' => 'Kitchen Sink Child Proof', 'slug' => 'kitchen-sink-child-proof', 'summary' => 'A child page for testimonials, logos, and proof widgets.', 'order' => 230],
            ['name' => 'Kitchen Sink Child Fallbacks', 'slug' => 'kitchen-sink-child-fallbacks', 'summary' => 'A child page for missing content and fallback states.', 'order' => 240],
            ['name' => 'Kitchen Sink Child Deep Link', 'slug' => 'kitchen-sink-child-deep-link', 'summary' => 'A child page for deep linking and anchor navigation.', 'order' => 250],
            ['name' => 'Kitchen Sink Child Stress Result', 'slug' => 'kitchen-sink-child-stress-result', 'summary' => 'A child page for final stress-render verification.', 'order' => 260],
        ];
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @param  array{name: string, slug: string, summary: string, order: int}  $data
     */
    private function createPage(Site $site, Layout $layout, EloquentCollection $languages, Page $parentPage, array $data): Page
    {
        /** @var Page $page */
        $page = resolve(PageCreator::class)->createPage([
            'name' => $data['name'],
            'layout_id' => $layout->getKey(),
            'type_key' => PageTypeEnum::Default,
            'parent_id' => $parentPage->getKey(),
            'visible_from' => now()->subDay()->format('Y-m-d'),
            'meta' => ['demo_fixture' => 'kitchen-sink-context'],
            'translations' => $this->translations($languages, $data),
        ], $site, $languages);

        $page->forceFill(['order' => $data['order']])->save();

        return $page->refresh();
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @param  array{name: string, slug: string, summary: string, order: int}  $data
     * @return array<string, array<string, mixed>>
     */
    private function translations(EloquentCollection $languages, array $data): array
    {
        $translations = [];

        foreach ($languages as $language) {
            $translations[(string) $language->code] = [
                'title' => $data['name'],
                'content' => '<p>' . e($data['summary']) . '</p>',
                'summary' => $data['summary'],
                'meta' => [
                    'slug' => $data['slug'],
                    'label' => $data['name'],
                    'exclude_from_footer' => true,
                    'demo_fixture' => 'kitchen-sink-context',
                ],
            ];
        }

        return $translations;
    }

    private function ensureDemoMedia(Model $model, string $name): void
    {
        if (! $model instanceof HasMedia || $model->getMedia(MediaCollectionEnum::Image->value)->isNotEmpty()) {
            return;
        }

        $path = $this->demoImagePath($name);

        if ($path !== null) {
            $model->addMedia($path)->preservingOriginal()->toMediaCollection(MediaCollectionEnum::Image->value);
        }
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

        return $fallbacks === false || $fallbacks === [] ? null : ($fallbacks[crc32($name) % count($fallbacks)] ?? null);
    }

    private function imageNameForIndex(int $index): string
    {
        $images = ['pricing', 'fresh-water', 'salt-water', 'birds', 'fish', 'reptiles', 'mammals', 'cats', 'dogs', 'sharks', 'owls', 'eagles'];

        return $images[($index - 1) % count($images)];
    }
}
