<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Models\Page;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use DateTimeInterface;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(Page $page, list<Page> $contextPages, list<array{widget_key: string, source_key: string, occurrence: int, stress_index: int, variant: string, lazy: bool}> $entries, list<string> $pageAssetWidgetKeys, string $latestPagesWidgetKey, int $contextAssetLimit)
 */
final class SyncKitchenSinkPageAssetsAction
{
    use AsObject;

    /**
     * @param  list<Page>  $contextPages
     * @param  list<array{widget_key: string, source_key: string, occurrence: int, stress_index: int, variant: string, lazy: bool}>  $entries
     * @param  list<string>  $pageAssetWidgetKeys
     */
    public function handle(Page $page, array $contextPages, array $entries, array $pageAssetWidgetKeys, string $latestPagesWidgetKey, int $contextAssetLimit): void
    {
        WidgetAsset::query()
            ->where('pageable_type', $page->getMorphClass())
            ->where('pageable_id', $page->getKey())
            ->delete();

        $rows = [];
        $timestamp = now();

        foreach ($entries as $order => $entry) {
            if ($entry['source_key'] === $latestPagesWidgetKey) {
                continue;
            }

            $widget = Widget::query()->firstWhere('key', $entry['widget_key']);

            if ($widget instanceof Widget) {
                $rows[] = $this->widgetAssetRow($page, $widget, $page, $order + 1, [
                    'scope' => 'kitchen-sink-demo',
                    'caption' => sprintf('Primary page asset for stress widget %03d', $order + 1),
                    'role' => 'primary-page',
                    'accent' => ['teal', 'blue', 'slate', 'amber'][$order % 4],
                ], $timestamp);
            }
        }

        $selectedPages = array_slice($contextPages, 0, $contextAssetLimit);
        $assetWidgetKeys = collect($entries)
            ->filter(static fn (array $entry): bool => in_array($entry['source_key'], $pageAssetWidgetKeys, true))
            ->pluck('widget_key')
            ->values();

        foreach ($assetWidgetKeys as $widgetKey) {
            $widget = Widget::query()->firstWhere('key', $widgetKey);

            if (! $widget instanceof Widget) {
                continue;
            }

            foreach ($selectedPages as $order => $assetPage) {
                $rows[] = $this->widgetAssetRow($page, $widget, $assetPage, $order + 1, [
                    'scope' => 'kitchen-sink-demo-page-selection',
                    'caption' => $assetPage->translation->title ?? $assetPage->name,
                    'content' => $assetPage->translation->summary ?? $assetPage->name,
                    'role' => 'selected-page',
                    'accent' => ['teal', 'blue', 'slate', 'amber'][$order % 4],
                    'crop_preset' => ['thumbnail', 'card', 'hero'][$order % 3],
                ], $timestamp);
            }
        }

        $latestWidgetKeys = collect($entries)
            ->filter(static fn (array $entry): bool => $entry['source_key'] === $latestPagesWidgetKey)
            ->pluck('widget_key')
            ->values();

        foreach ($latestWidgetKeys as $widgetKey) {
            $widget = Widget::query()->firstWhere('key', $widgetKey);

            if (! $widget instanceof Widget) {
                continue;
            }

            foreach ($selectedPages as $order => $assetPage) {
                $rows[] = $this->widgetAssetRow($page, $widget, $assetPage, $order + 1, [
                    'scope' => 'kitchen-sink-livewire-latest-pages',
                    'caption' => $assetPage->translation->title ?? $assetPage->name,
                    'content' => $assetPage->translation->summary ?? $assetPage->name,
                    'role' => 'selected-page',
                ], $timestamp);
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            WidgetAsset::query()->insert($chunk);
        }
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
}
