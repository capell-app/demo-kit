<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Language;
use Capell\LayoutBuilder\Enums\WidgetComponentEnum;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\MediaLibrary\HasMedia;

/** @method static void run(EloquentCollection<int, Language> $languages) */
final class CreateKitchenSinkSourceWidgetsAction
{
    use AsObject;

    /**
     * @param  EloquentCollection<int, Language>  $languages
     */
    public function handle(EloquentCollection $languages): void
    {
        $type = resolve(TypeCreator::class)->defaultWidgetType();
        $sources = [
            'kitchen-sink-hero-top' => [
                'name' => 'Kitchen Sink Opening Hero',
                'meta' => ['component' => WidgetComponentEnum::ApHeroBanner->value, 'primary_button_text' => 'Inspect widget matrix', 'primary_button_url' => '#kitchen-sink-widget-matrix', 'secondary_button_text' => 'Test lazy fragments', 'secondary_button_url' => '#kitchen-sink-lazy-fragments', 'hero_height' => 'clamp(34rem, 72vh, 48rem)', 'hero_asset_source' => 'widget', 'heading_tag' => 'h2', 'margin' => ['none']],
                'image' => 'pricing',
                'livewire' => false,
            ],
            'kitchen-sink-hero-middle' => [
                'name' => 'Kitchen Sink Middle Hero',
                'meta' => ['component' => WidgetComponentEnum::ApHeroBanner->value, 'primary_button_text' => 'Continue stress pass', 'primary_button_url' => '#kitchen-sink-lazy-fragments', 'secondary_button_text' => 'Review media', 'secondary_button_url' => '#kitchen-sink-media-density', 'hero_height' => '32rem', 'hero_asset_source' => 'widget', 'heading_tag' => 'h2', 'margin' => ['xl']],
                'image' => 'fresh-water',
                'livewire' => false,
            ],
            'kitchen-sink-hero-deep' => [
                'name' => 'Kitchen Sink Deep Hero',
                'meta' => ['component' => WidgetComponentEnum::ApHeroBanner->value, 'primary_button_text' => 'Finish render pass', 'primary_button_url' => '#footer', 'secondary_button_text' => 'Open child page', 'secondary_button_url' => '#', 'hero_height' => '30rem', 'hero_asset_source' => 'widget', 'heading_tag' => 'h2', 'margin' => ['xl']],
                'image' => 'salt-water',
                'livewire' => false,
            ],
            'kitchen-sink-livewire-stress' => [
                'name' => 'Kitchen Sink Livewire Stress Widget',
                'meta' => ['component' => 'capell-demo-kit.widget.kitchen-sink-livewire-stress', 'livewire' => true, 'margin' => ['lg'], 'padding' => ['lg']],
                'image' => null,
                'livewire' => true,
            ],
            'kitchen-sink-livewire-latest-pages' => [
                'name' => 'Kitchen Sink Livewire Latest Pages Widget',
                'meta' => ['component' => 'capell.widget.pages', 'livewire' => true, 'limit' => 12, 'pagination' => true, 'with_image' => true, 'with_link_text' => true, 'with_summary' => true, 'margin' => ['lg'], 'padding' => ['lg']],
                'image' => null,
                'livewire' => true,
            ],
        ];

        foreach ($sources as $key => $source) {
            /** @var Widget $widget */
            $widget = Widget::query()->updateOrCreate(
                ['key' => $key],
                ['name' => $source['name'], 'blueprint_id' => $type->getKey(), 'meta' => $source['meta'], 'is_livewire' => $source['livewire'], 'status' => true],
            );

            foreach ($languages as $language) {
                $widget->translations()->updateOrCreate(
                    ['language_id' => $language->getKey()],
                    ['title' => $source['name'], 'content' => '<p>Purpose-built Kitchen Sink fixture content for layout, media, and runtime stress testing.</p>'],
                );
            }

            if (is_string($source['image'] ?? null)) {
                $this->ensureDemoMedia($widget, $source['image']);
            }
        }
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
        $path = File::exists($preferred) ? $preferred : (($fallbacks = glob($directory . '/*.jpg')) !== false && $fallbacks !== [] ? ($fallbacks[crc32($name) % count($fallbacks)] ?? null) : null);

        if (is_string($path)) {
            $model->addMedia($path)->preservingOriginal()->toMediaCollection(MediaCollectionEnum::BackgroundImage->value);
        }
    }
}
