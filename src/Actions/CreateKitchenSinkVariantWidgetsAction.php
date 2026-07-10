<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Language;
use Capell\LayoutBuilder\Models\Widget;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\MediaLibrary\HasMedia;

/**
 * @method static void run(EloquentCollection<int, Language> $languages, list<array{widget_key: string, source_key: string, occurrence: int, stress_index: int, variant: string, lazy: bool}> $entries)
 */
final class CreateKitchenSinkVariantWidgetsAction
{
    use AsObject;

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @param  list<array{widget_key: string, source_key: string, occurrence: int, stress_index: int, variant: string, lazy: bool}>  $entries
     */
    public function handle(EloquentCollection $languages, array $entries): void
    {
        foreach ($entries as $entry) {
            $sourceWidget = Widget::query()->firstWhere('key', $entry['source_key']);

            if (! $sourceWidget instanceof Widget) {
                continue;
            }

            /** @var Widget $widget */
            $widget = Widget::query()->updateOrCreate(
                ['key' => $entry['widget_key']],
                [
                    'name' => sprintf('Kitchen Sink %03d: %s', $entry['stress_index'], $sourceWidget->name),
                    'blueprint_id' => $sourceWidget->blueprint_id,
                    'component' => $sourceWidget->component,
                    'component_item' => $sourceWidget->component_item,
                    'is_livewire' => $sourceWidget->is_livewire,
                    'view_file' => $sourceWidget->view_file,
                    'meta' => $this->variantMeta($sourceWidget, $entry),
                    'admin' => [...($sourceWidget->admin ?? []), 'kitchen_sink_source_key' => $entry['source_key']],
                    'status' => true,
                ],
            );

            foreach ($languages as $language) {
                $widget->translations()->updateOrCreate(
                    ['language_id' => $language->getKey()],
                    [
                        'title' => sprintf('Kitchen Sink %03d: %s', $entry['stress_index'], Str::headline($entry['source_key'])),
                        'content' => $this->variantContent($entry),
                    ],
                );
            }

            if (in_array($entry['source_key'], ['kitchen-sink-hero-top', 'kitchen-sink-hero-middle', 'kitchen-sink-hero-deep', 'banner-image'], true)) {
                $this->ensureDemoMedia($widget, $this->imageNameForIndex($entry['stress_index']));
            }
        }
    }

    /** @param array{widget_key: string, source_key: string, occurrence: int, stress_index: int, variant: string, lazy: bool} $entry */
    private function variantMeta(Widget $sourceWidget, array $entry): array
    {
        $stressIndex = $entry['stress_index'];
        $columns = [1, 2, 3, 4][$stressIndex % 4];

        return [
            ...($sourceWidget->meta ?? []),
            'kitchen_sink' => ['source_key' => $entry['source_key'], 'stress_index' => $stressIndex, 'variant' => $entry['variant']],
            'align' => ['left', 'center', 'right'][$stressIndex % 3],
            'background_color' => ['#ffffff', '#f8fafc', '#0f766e', '#334155'][$stressIndex % 4],
            'columns' => $columns,
            'content_divider' => $stressIndex % 2 === 0 ? 'below_heading' : 'none',
            'heading_size' => 'h2',
            'heading_style' => $stressIndex % 3 === 0 ? 'primary' : 'secondary',
            'limit' => $entry['source_key'] === 'kitchen-sink-livewire-latest-pages' ? 12 : max(4, min(12, $columns * 3)),
            'margin' => [$stressIndex % 2 === 0 ? 'lg' : 'xl'],
            'pagination' => in_array($entry['source_key'], ['latest-pages', 'pages-card', 'children', 'siblings', 'kitchen-sink-livewire-latest-pages'], true),
            'padding' => [$stressIndex % 2 === 0 ? 'md' : 'lg'],
            'spacing' => ['sm', 'md', 'lg'][$stressIndex % 3],
            'with_children_count' => true,
            'with_image' => true,
            'with_link_text' => true,
            'with_summary' => true,
            'carousel_arrows' => true,
            'carousel_auto_delay' => 3500 + ($stressIndex % 4) * 500,
            'carousel_auto_play' => $stressIndex % 2 === 0,
            'carousel_drag' => true,
            'carousel_effect' => $stressIndex % 3 === 0 ? 'fade' : 'slide',
            'carousel_loop' => true,
            'carousel_pagination' => true,
            'carousel_pause_on_hover' => true,
            'primary_button_text' => 'Open stress case ' . $stressIndex,
            'primary_button_url' => '#kitchen-sink-widget-' . $stressIndex,
            'secondary_button_text' => $entry['lazy'] ? 'Lazy fragment' : 'Eager render',
            'secondary_button_url' => '#kitchen-sink-lazy-fragments',
        ];
    }

    /** @param array{widget_key: string, source_key: string, occurrence: int, stress_index: int, variant: string, lazy: bool} $entry */
    private function variantContent(array $entry): string
    {
        return sprintf(
            '<p><strong>%s.</strong> Stress case %03d exercises %s with %s, image-backed assets, dense copy, link text, pagination settings, and contrast-safe public rendering.</p>',
            e(Str::headline($entry['variant'])),
            $entry['stress_index'],
            e(Str::headline($entry['source_key'])),
            e($entry['lazy'] ? 'deferred public render' : 'eager public render'),
        );
    }

    private function ensureDemoMedia(Model $model, string $name): void
    {
        if (! $model instanceof HasMedia || $model->getMedia(MediaCollectionEnum::BackgroundImage->value)->isNotEmpty()) {
            return;
        }

        $directory = realpath(__DIR__ . '/../../demo/img');

        if ($directory === false) {
            return;
        }

        $preferred = $directory . '/' . Str::slug($name) . '.jpg';
        $fallbacks = glob($directory . '/*.jpg');
        $path = File::exists($preferred) ? $preferred : ($fallbacks !== false && $fallbacks !== [] ? ($fallbacks[crc32($name) % count($fallbacks)] ?? null) : null);

        if (is_string($path)) {
            $model->addMedia($path)->preservingOriginal()->toMediaCollection(MediaCollectionEnum::BackgroundImage->value);
        }
    }

    private function imageNameForIndex(int $index): string
    {
        $images = ['pricing', 'fresh-water', 'salt-water', 'birds', 'fish', 'reptiles', 'mammals', 'cats', 'dogs', 'sharks', 'owls', 'eagles'];

        return $images[($index - 1) % count($images)];
    }
}
