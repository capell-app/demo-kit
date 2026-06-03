<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support;

use Capell\Core\Contracts\Pageable;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Illuminate\Support\Collection;

final class DemoPageContentAssetSections
{
    /**
     * @return list<array<string, mixed>>
     */
    public function resolve(Widget $widget, ?Pageable $page, string $container, int $occurrence): array
    {
        if (! $page instanceof Pageable || ! $widget->relationLoaded('assets')) {
            return [];
        }

        return $widget->assets
            ->filter(fn (mixed $asset): bool => $asset instanceof WidgetAsset)
            ->filter(fn (WidgetAsset $asset): bool => $this->belongsToPosition($asset, $page, $container, $occurrence))
            ->filter(fn (WidgetAsset $asset): bool => ($asset->meta['demo_kit_seed'] ?? false) === true)
            ->sortBy(fn (WidgetAsset $asset): int => (int) $asset->order)
            ->map(fn (WidgetAsset $asset): array => $this->normalize($asset))
            ->filter(fn (array $section): bool => $section !== [])
            ->values()
            ->all();
    }

    private function belongsToPosition(WidgetAsset $asset, Pageable $page, string $container, int $occurrence): bool
    {
        return $asset->pageable_type === $page->getMorphClass()
            && (string) $asset->pageable_id === (string) $page->getKey()
            && $asset->container === $container
            && (int) ($asset->occurrence ?? 1) === $occurrence;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(WidgetAsset $asset): array
    {
        $meta = is_array($asset->meta) ? $asset->meta : [];
        $variant = $this->stringValue($meta['variant'] ?? null);
        $title = $this->stringValue($meta['title'] ?? null);

        if ($variant === '' || $title === '') {
            return [];
        }

        return [
            'variant' => $variant,
            'layout' => $this->stringValue($meta['layout'] ?? $variant),
            'eyebrow' => $this->stringValue($meta['eyebrow'] ?? ''),
            'title' => $title,
            'intro' => $this->stringValue($meta['intro'] ?? ''),
            'items' => $this->items($meta['items'] ?? []),
            'metrics' => $this->items($meta['metrics'] ?? []),
            'steps' => $this->items($meta['steps'] ?? []),
            'filters' => $this->strings($meta['filters'] ?? []),
            'cta' => is_array($meta['cta'] ?? null) ? $meta['cta'] : [],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function items(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values((new Collection($items))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'label' => $this->stringValue($item['label'] ?? ''),
                'title' => $this->stringValue($item['title'] ?? ''),
                'copy' => $this->stringValue($item['copy'] ?? ''),
                'value' => $this->stringValue($item['value'] ?? ''),
                'href' => $this->stringValue($item['href'] ?? ''),
            ])
            ->values()
            ->all());
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values((new Collection($items))
            ->map(fn (mixed $item): string => $this->stringValue($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->values()
            ->all());
    }

    private function stringValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }
}
