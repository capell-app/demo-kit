<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support;

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Support\Security\PublicHtmlSanitizer;
use Capell\DemoKit\Actions\InstallKitchenSinkDemoPageAction;
use Capell\LayoutBuilder\Contracts\PublicLayoutWidgetPayloadContributor;
use Capell\LayoutBuilder\Models\Widget;
use Illuminate\Database\Eloquent\Model;

final class KitchenSinkPublicLayoutWidgetPayloadContributor implements PublicLayoutWidgetPayloadContributor
{
    /**
     * @var array<int, string>
     */
    private const array ReferenceSourceWidgetKeys = [
        'kitchen-sink-structured-text',
        'kitchen-sink-rich-text',
        'kitchen-sink-data-display',
        'kitchen-sink-interactions',
        'kitchen-sink-embeds',
        'kitchen-sink-forms',
        'kitchen-sink-utility-states',
    ];

    public function priority(): int
    {
        return 50;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Widget $widget, Page $page, Language $language, string $containerKey, int $occurrence): array
    {
        if (! str_starts_with($widget->key, 'kitchen-sink-')) {
            return [];
        }

        $translation = $this->translation($widget, $language);

        return [
            'widget_key' => $widget->key,
            'content' => $this->sanitizeHtml($translation instanceof Model ? $translation->getAttribute('content') : null),
        ];
    }

    public function html(Widget $widget, Page $page, Language $language, string $containerKey, int $occurrence): ?string
    {
        $sourceWidgetKey = $this->sourceWidgetKey($widget);

        if (! in_array($sourceWidgetKey, self::ReferenceSourceWidgetKeys, true)) {
            return $this->fallbackHtml($widget, $language, $sourceWidgetKey);
        }

        $sections = is_array($widget->meta['sections'] ?? null) ? $widget->meta['sections'] : [];

        if ($sections === []) {
            $sections = $this->referenceSections($sourceWidgetKey);
        }

        $family = e((string) ($widget->meta['family'] ?? 'reference'));
        $translation = $this->translation($widget, $language);
        $html = '<section class="capell-kitchen-sink-reference">';

        if ($translation instanceof Model) {
            $html .= '<div>' . $this->sanitizeHtml($translation->getAttribute('content')) . '</div>';
        }

        foreach ($sections as $section) {
            if (is_array($section)) {
                $html .= $this->sectionHtml($section, $family);
            }
        }

        return $html . '</section>';
    }

    private function fallbackHtml(Widget $widget, Language $language, string $sourceWidgetKey): ?string
    {
        if (! str_starts_with($widget->key, 'kitchen-sink-')) {
            return null;
        }

        $translation = $this->translation($widget, $language);

        $title = e((string) ($translation instanceof Model ? $translation->getAttribute('title') : $widget->name));
        $content = $this->sanitizeHtml($translation instanceof Model ? $translation->getAttribute('content') : null);
        $source = e(str($sourceWidgetKey)->headline()->toString());
        $variant = e((string) data_get($widget->meta, 'kitchen_sink.variant', 'lazy fragment'));
        $stressIndex = e((string) data_get($widget->meta, 'kitchen_sink.stress_index', ''));

        return '<section class="capell-kitchen-sink-fragment" data-source="' . e($sourceWidgetKey) . '">'
            . '<header>'
            . '<p>' . $source . ' / ' . $variant . '</p>'
            . '<h2>' . $title . '</h2>'
            . '</header>'
            . '<dl>'
            . '<div><dt>Source</dt><dd>' . $source . '</dd></div>'
            . '<div><dt>Variant</dt><dd>' . $variant . '</dd></div>'
            . '<div><dt>Case</dt><dd>' . ($stressIndex !== '' ? $stressIndex : 'deferred') . '</dd></div>'
            . '</dl>'
            . '<div class="capell-kitchen-sink-fragment__content">' . $content . '</div>'
            . '</section>';
    }

    private function sourceWidgetKey(Widget $widget): string
    {
        if (in_array($widget->key, self::ReferenceSourceWidgetKeys, true)) {
            return $widget->key;
        }

        $matches = [];

        if (preg_match('/\Akitchen-sink-\d{3}-(?<source>.+)\z/', $widget->key, $matches) !== 1) {
            return $widget->key;
        }

        return is_string($matches['source'] ?? null) ? $matches['source'] : $widget->key;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function sectionHtml(array $section, string $family): string
    {
        $sectionKey = is_string($section['key'] ?? null) ? $section['key'] : 'reference-section';
        $key = e($sectionKey);
        $heading = e((string) ($section['heading'] ?? 'Reference section'));
        $summary = e((string) ($section['summary'] ?? $heading . ' reference section.'));

        return '<article id="' . $key . '">'
            . '<header><p>' . $family . '</p><h2 id="' . $key . '-heading">' . $heading . '</h2><p>' . $summary . '</p></header>'
            . $this->accessibilitySample($sectionKey)
            . '</article>';
    }

    private function accessibilitySample(string $key): string
    {
        $escapedKey = e($key);

        return match ($key) {
            'faq-accordion' => '<button type="button" aria-expanded="false" aria-controls="faq-panel-demo" id="faq-button-demo">What does this prove?</button><div id="faq-panel-demo" role="region" aria-labelledby="faq-button-demo" hidden><p>The accordion uses a native button and labelled panel.</p></div>',
            'tabs' => '<div role="tablist" aria-label="Demo tab set"><button type="button" role="tab" aria-selected="true" aria-controls="tab-panel-overview" id="tab-overview">Overview</button><button type="button" role="tab" aria-selected="false" aria-controls="tab-panel-details" id="tab-details" tabindex="-1">Details</button></div><div id="tab-panel-overview" role="tabpanel" aria-labelledby="tab-overview"><p>Tab panels stay labelled and keyboard reachable.</p></div>',
            'table-of-data', 'complex-table' => '<table><caption>' . e(str($key)->headline()->toString()) . ' example</caption><thead><tr><th scope="col">Pattern</th><th scope="col">Status</th></tr></thead><tbody><tr><th scope="row">Semantic HTML</th><td>Ready</td></tr></tbody></table>',
            'form-field-demo', 'full-form' => '<form action="#" method="post"><label for="' . $escapedKey . '-email">Work email</label><input id="' . $escapedKey . '-email" name="email" type="email" aria-describedby="' . $escapedKey . '-email-help ' . $escapedKey . '-email-error"><p id="' . $escapedKey . '-email-help">Use a visible label and helper text.</p><p id="' . $escapedKey . '-email-error" role="alert">Example validation message.</p><button type="button">Submit demo form</button></form>',
            default => '<p aria-labelledby="' . $escapedKey . '-heading">Reference output.</p>',
        };
    }

    /** @return list<array{key: string, heading: string, summary: string}> */
    private function referenceSections(string $sourceWidgetKey): array
    {
        $ranges = [
            'kitchen-sink-structured-text' => [0, 3],
            'kitchen-sink-rich-text' => [3, 8],
            'kitchen-sink-data-display' => [11, 8],
            'kitchen-sink-interactions' => [19, 5],
            'kitchen-sink-embeds' => [24, 5],
            'kitchen-sink-forms' => [29, 6],
            'kitchen-sink-utility-states' => [35, 5],
        ];
        $range = $ranges[$sourceWidgetKey] ?? null;

        if ($range === null) {
            return [];
        }

        return array_values(array_map(static fn (string $heading): array => [
            'key' => str($heading)->slug()->toString(),
            'heading' => $heading,
            'summary' => $heading . ' reference section.',
        ], array_slice(InstallKitchenSinkDemoPageAction::sectionHeadings(), $range[0], $range[1])));
    }

    private function sanitizeHtml(mixed $html): string
    {
        return resolve(PublicHtmlSanitizer::class)->sanitize(is_string($html) ? $html : '');
    }

    private function translation(Widget $widget, Language $language): ?Model
    {
        if ($widget->relationLoaded('translation') && $widget->translation instanceof Model) {
            return $widget->translation;
        }

        if (! $widget->relationLoaded('translations')) {
            return null;
        }

        $translation = $widget->translations->first(
            static fn (Model $candidate): bool => $candidate->getAttribute('language_id') === $language->getKey(),
        );

        return $translation instanceof Model ? $translation : null;
    }
}
