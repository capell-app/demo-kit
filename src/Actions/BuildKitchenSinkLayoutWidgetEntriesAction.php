<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\LayoutBuilder\Data\LayoutWidgetCatalogDefinitionData;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<array{widget_key: string, source_key: string, occurrence: int, stress_index: int, variant: string, lazy: bool}> run(int $targetWidgetCount, int $eagerWidgetLimit)
 */
final class BuildKitchenSinkLayoutWidgetEntriesAction
{
    use AsObject;

    /**
     * @return list<array{widget_key: string, source_key: string, occurrence: int, stress_index: int, variant: string, lazy: bool}>
     */
    public function handle(int $targetWidgetCount, int $eagerWidgetLimit): array
    {
        $sourceKeys = [
            'kitchen-sink-hero-top',
            'kitchen-sink-structured-text',
            'breadcrumbs',
            'announcement-bar',
            'page-content',
            'snippet',
            'gallery',
            'media-carousel',
            'pages-card',
            'kitchen-sink-livewire-stress',
            'kitchen-sink-livewire-latest-pages',
            'assets',
            'assets-accordion',
            'assets-banner',
            'asset-features',
            'asset-testimonials',
            'widget-navigation',
            'widget-navigation-tabs',
            'banner-image',
            'kitchen-sink-hero-middle',
            'latest-pages',
            'children',
            'siblings',
            'assets-widget',
            'default',
            ...array_diff($this->widgetFamilyKeys(), ['kitchen-sink-structured-text']),
            'kitchen-sink-hero-deep',
            ...collect([
                ...LayoutWidgetCatalogDefinitionData::defaultCatalog(),
                ...LayoutWidgetCatalogDefinitionData::extraCatalog(),
            ])->map(static fn (LayoutWidgetCatalogDefinitionData $definition): string => $definition->key)->all(),
        ];

        $entries = [];
        $sourceOccurrences = [];
        $targetWidgetCount = max(1, $targetWidgetCount);
        $eagerWidgetLimit = max(1, $eagerWidgetLimit);

        while (count($entries) < $targetWidgetCount) {
            foreach (array_values(array_unique($sourceKeys)) as $sourceKey) {
                $sourceOccurrences[$sourceKey] = ($sourceOccurrences[$sourceKey] ?? 0) + 1;
                $stressIndex = count($entries) + 1;

                $entries[] = [
                    'widget_key' => sprintf('kitchen-sink-%03d-%s', $stressIndex, Str::slug($sourceKey)),
                    'source_key' => $sourceKey,
                    'occurrence' => $sourceOccurrences[$sourceKey],
                    'stress_index' => $stressIndex,
                    'variant' => $this->variantName($stressIndex),
                    'lazy' => $stressIndex > $eagerWidgetLimit,
                ];

                if (count($entries) >= $targetWidgetCount) {
                    break;
                }
            }
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function widgetFamilyKeys(): array
    {
        return [
            'kitchen-sink-rich-text',
            'kitchen-sink-data-display',
            'kitchen-sink-interactions',
            'kitchen-sink-embeds',
            'kitchen-sink-forms',
            'kitchen-sink-utility-states',
        ];
    }

    private function variantName(int $stressIndex): string
    {
        return [
            'baseline',
            'dense content',
            'image heavy',
            'high contrast',
            'pagination',
            'carousel',
            'compact',
            'wide',
            'long label',
            'empty state guard',
            'dark surface',
            'nested assets',
        ][($stressIndex - 1) % 12];
    }
}
