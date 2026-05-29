<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support;

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\LayoutBuilder\Contracts\PublicBlockPayloadContributor;
use Capell\LayoutBuilder\Models\Widget;

final class KitchenSinkPublicBlockPayloadContributor implements PublicBlockPayloadContributor
{
    /**
     * @var array<int, string>
     */
    private const array BlockKeys = [
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
    public function data(Widget $block, Page $page, Language $language, string $containerKey, int $occurrence): array
    {
        return [];
    }

    public function html(Widget $block, Page $page, Language $language, string $containerKey, int $occurrence): ?string
    {
        if (! in_array($block->key, self::BlockKeys, true)) {
            return null;
        }

        $sourceBlock = Widget::query()
            ->with('translations')
            ->find($block->getKey());

        if (! $sourceBlock instanceof Widget) {
            return null;
        }

        $sections = is_array($sourceBlock->meta['sections'] ?? null) ? $sourceBlock->meta['sections'] : [];

        if ($sections === []) {
            return null;
        }

        $family = e((string) ($sourceBlock->meta['family'] ?? 'reference'));
        $translation = $sourceBlock->translations->firstWhere('language_id', $language->getKey())
            ?? $sourceBlock->translations->first();
        $html = '<section class="capell-kitchen-sink-reference">';

        if ($translation !== null) {
            $html .= '<div>' . $translation->content . '</div>';
        }

        foreach ($sections as $section) {
            if (is_array($section)) {
                $html .= $this->sectionHtml($section, $family);
            }
        }

        return $html . '</section>';
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function sectionHtml(array $section, string $family): string
    {
        $key = e((string) ($section['key'] ?? 'reference-section'));
        $heading = e((string) ($section['heading'] ?? 'Reference section'));
        $summary = e((string) ($section['summary'] ?? $heading . ' reference section.'));

        return '<article id="' . $key . '">'
            . '<header><p>' . $family . '</p><h2 id="' . $key . '-heading">' . $heading . '</h2><p>' . $summary . '</p></header>'
            . $this->accessibilitySample((string) ($section['key'] ?? ''))
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
}
