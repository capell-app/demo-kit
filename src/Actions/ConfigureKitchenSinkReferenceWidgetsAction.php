<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Models\Language;
use Capell\LayoutBuilder\Models\Widget;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(EloquentCollection<int, \Capell\Core\Models\Language> $languages)
 */
final class ConfigureKitchenSinkReferenceWidgetsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  EloquentCollection<int, Language>  $languages
     */
    public function handle(EloquentCollection $languages): void
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

        foreach (self::widgetFamilies() as $key => $family) {
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

    /**
     * @return array<string, array{family: string, title: string, summary: string, headings: array<int, string>}>
     */
    private static function widgetFamilies(): array
    {
        return [
            'kitchen-sink-structured-text' => ['family' => 'Structured text', 'title' => 'Structured content reference', 'summary' => 'Hero, breadcrumbs, and table of contents patterns.', 'headings' => array_slice(InstallKitchenSinkDemoPageAction::sectionHeadings(), 0, 3)],
            'kitchen-sink-rich-text' => ['family' => 'Rich text', 'title' => 'Rich text reference', 'summary' => 'Text, hierarchy, quote, code, list, and callout patterns.', 'headings' => array_slice(InstallKitchenSinkDemoPageAction::sectionHeadings(), 3, 8)],
            'kitchen-sink-data-display' => ['family' => 'Data display', 'title' => 'Data display reference', 'summary' => 'Card, listing, teaser, feature, statistics, proof, logo, and pricing patterns.', 'headings' => array_slice(InstallKitchenSinkDemoPageAction::sectionHeadings(), 11, 8)],
            'kitchen-sink-interactions' => ['family' => 'Interactions', 'title' => 'Interaction reference', 'summary' => 'Accordion, tabs, carousel, timeline, and process behavior contracts.', 'headings' => array_slice(InstallKitchenSinkDemoPageAction::sectionHeadings(), 19, 5)],
            'kitchen-sink-embeds' => ['family' => 'Embeds', 'title' => 'Embeds reference', 'summary' => 'Gallery, media, map, and table contracts.', 'headings' => array_slice(InstallKitchenSinkDemoPageAction::sectionHeadings(), 24, 5)],
            'kitchen-sink-forms' => ['family' => 'Forms', 'title' => 'Forms reference', 'summary' => 'Complex table, search, filter, field, full-form, and CTA examples.', 'headings' => array_slice(InstallKitchenSinkDemoPageAction::sectionHeadings(), 29, 6)],
            'kitchen-sink-utility-states' => ['family' => 'Utility states', 'title' => 'Utility states reference', 'summary' => 'Alert, embed, empty, error, and footer state contracts.', 'headings' => array_slice(InstallKitchenSinkDemoPageAction::sectionHeadings(), 35, 5)],
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
                'Content' => 'Render public copy from widget translations, meta, or page-scoped assets.',
                'Variant rules' => 'Keep variants explicit in widget data and avoid internal selectors.',
                'Behavior' => 'Prefer native controls, then enhance progressively.',
                'Accessibility' => 'Expose labels, captions, scoped headers, and clear text equivalents.',
            ],
        ], $headings);
    }
}
