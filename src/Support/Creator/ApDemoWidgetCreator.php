<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Site;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Enums\WidgetComponentEnum;
use Capell\LayoutBuilder\Enums\WidgetTypeEnum;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Support\Creator\WidgetCreator;
use Illuminate\Database\Eloquent\Collection;

abstract class ApDemoWidgetCreator extends HomepageDemoWidgetCreator
{
    public function createApHeroBannerWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::HeroBanner)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
                ->firstWhere('key', WidgetTypeEnum::Default);
        $widgetType = $this->requireBlueprint($widgetType, 'AP hero banner widget');

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'ap-hero-banner'], [
            'name' => 'AP Hero Banner',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApHeroBanner,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Product Hero',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApHeroBanner,
                'primary_button_text' => 'Explore the demo',
                'primary_button_url' => '/admin',
                'secondary_button_text' => 'Read the docs',
                'secondary_button_url' => '/docs/installation',
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Capell CMS',
                    'content' => '<p>The Laravel and Filament CMS operating system for multi-site publishing, visual layout building, package-owned frontends, and static-fast delivery.</p>',
                ],
            );
        }

        $this->createMedia($widget, 'sharks', collection: MediaCollectionEnum::BackgroundImage);

        return $widget;
    }

    public function createApCardGridWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::CardGrid)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
                ->firstWhere('key', WidgetTypeEnum::Default);
        $widgetType = $this->requireBlueprint($widgetType, 'AP card grid widget');

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'ap-card-grid'], [
            'name' => 'Capell Capability Cards',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApCardGrid,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Capability Cards',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApCardGrid,
                'columns' => 3,
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'A complete CMS foundation, not a theme demo',
                    'content' => '<p>Capell brings the content model, admin workflow, frontend runtime, and release checks together so teams can ship production sites without stitching every layer by hand.</p>',
                ],
            );
        }

        $widget->assets()->delete();

        $cards = [
            ['icon' => 'heroicon-o-circle-stack', 'title' => 'Structured content engine', 'description' => 'Model pages, sections, widgets, media, translations, and relationships with clear Laravel records instead of hardcoded templates.', 'link_text' => 'Inspect the model', 'link_url' => '/admin'],
            ['icon' => 'heroicon-o-rectangle-group', 'title' => 'Visual layout builder', 'description' => 'Compose real frontend sections from editable widgets while keeping rendering package-owned and predictable.', 'link_text' => 'Edit the homepage', 'link_url' => '/admin'],
            ['icon' => 'heroicon-o-bolt', 'title' => 'Static-fast delivery', 'description' => 'Generate frontend HTML, verify runtime assets, and keep public pages fast without giving up CMS control.', 'link_text' => 'Run doctor', 'link_url' => '/docs/installation'],
        ];

        foreach ($cards as $card) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $card['title']], [
                'meta' => [
                    'icon' => $card['icon'],
                    'link_text' => $card['link_text'],
                    'link_url' => $card['link_url'],
                ],
            ]);

            foreach (Site::getDefault()->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $card['title'], 'content' => sprintf('<p>%s</p>', $card['description'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createApFeatureListWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::FeatureList)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
                ->firstWhere('key', WidgetTypeEnum::Default);
        $widgetType = $this->requireBlueprint($widgetType, 'AP feature list widget');

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'ap-feature-list'], [
            'name' => 'Capell Workflow Feature List',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApFeatureList,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Workflow Feature List',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApFeatureList,
                'layout' => 'grid',
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Everything visible is backed by editable records',
                    'content' => '<p>The default homepage is deliberately assembled from Capell widgets, assets, media, and translations so the admin experience proves the frontend is not a static mockup.</p>',
                ],
            );
        }

        $widget->assets()->delete();

        $features = [
            ['icon' => 'heroicon-o-language', 'title' => 'Page translations', 'description' => 'Hero titles, body copy, SEO fields, and language variants live in translation records.'],
            ['icon' => 'heroicon-o-photo', 'title' => 'Media-driven surfaces', 'description' => 'Hero backgrounds, gallery items, cards, and section imagery resolve through Capell media records.'],
            ['icon' => 'heroicon-o-pencil-square', 'title' => 'Editor-owned sections', 'description' => 'Homepage cards, feature rows, FAQs, testimonials, and CTAs are all admin-managed content.'],
            ['icon' => 'heroicon-o-shield-check', 'title' => 'Release diagnostics', 'description' => 'Doctor checks verify the demo, homepage, widgets, runtime manifests, and generated frontend CSS.'],
        ];

        foreach ($features as $feature) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $feature['title']], [
                'meta' => ['icon' => $feature['icon']],
            ]);

            foreach (Site::getDefault()->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $feature['title'], 'content' => sprintf('<p>%s</p>', $feature['description'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createFeatureListWidget(): Widget
    {
        $widget = resolve(WidgetCreator::class)->featuresWidget();

        foreach (Site::getDefault()->languages ?? [] as $language) {
            $widget->translations()->firstOrCreate(
                ['language_id' => $language->id],
                ['title' => 'Features'],
            );
        }

        if ($widget->assets()->exists()) {
            return $widget;
        }

        $features = [
            ['icon' => 'heroicon-o-light-bulb', 'title' => 'Reusable CMS Patterns', 'description' => 'We use Laravel packages, Filament resources, and reusable widgets to keep CMS implementations maintainable.'],
            ['icon' => 'heroicon-o-academic-cap', 'title' => 'Deep Expertise', 'description' => 'Our team brings deep industry knowledge and experience to every project.'],
            ['icon' => 'heroicon-o-user-group', 'title' => 'Client-Centric Approach', 'description' => "We prioritize our clients' needs and work collaboratively to achieve their goals."],
            ['icon' => 'heroicon-o-chart-bar', 'title' => 'Operational Checks', 'description' => 'We ship with checks for content, assets, cache, and frontend output so teams can verify each release.'],
            ['icon' => 'heroicon-o-sparkles', 'title' => 'Sustainable Practices', 'description' => 'We are committed to sustainable practices that benefit our clients and the environment.'],
            ['icon' => 'heroicon-o-globe-alt', 'title' => 'Global Reach', 'description' => 'Our global presence allows us to serve clients across diverse markets and industries.'],
        ];

        foreach ($features as $feature) {
            $section = $this->contentModel::query()->firstOrCreate(['name' => $feature['title']], [
                'meta' => ['icon' => $feature['icon']],
            ]);

            foreach (Site::getDefault()->languages ?? [] as $language) {
                $this->translationsFor($section)->firstOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $feature['title'], 'content' => sprintf('<p>%s</p>', $feature['description'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createApCtaSectionWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::CTASection)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
                ->firstWhere('key', WidgetTypeEnum::Default);
        $widgetType = $this->requireBlueprint($widgetType, 'AP CTA section widget');

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'ap-cta-section'], [
            'name' => 'AP CTA Section',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApCTASection,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Showcase CTA',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApCTASection,
                'primary_button_text' => 'Open the admin',
                'primary_button_url' => '/admin',
                'secondary_button_text' => 'Run install doctor',
                'secondary_button_url' => '/docs/installation',
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'A demo site that proves the CMS stack is wired',
                    'content' => '<p>Change the homepage in Filament, regenerate the frontend, and use Capell doctor to confirm content, assets, runtime JavaScript, and layouts are all healthy.</p>',
                ],
            );
        }

        return $widget;
    }

    public function createApImageGalleryWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::ImageGallery)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
                ->firstWhere('key', WidgetTypeEnum::Default);
        $widgetType = $this->requireBlueprint($widgetType, 'AP image gallery widget');

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'ap-image-gallery'], [
            'name' => 'AP Image Gallery',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApImageGallery,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Media Gallery',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApImageGallery,
                'layout' => 'grid',
                'columns' => 3,
                'lightbox' => true,
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Media that stays editable',
                    'content' => '<p>Use the gallery to verify image records, captions, crops, and frontend rendering stay connected from admin to public page.</p>',
                ],
            );
        }

        if ($widget->assets()->exists()) {
            return $widget;
        }

        for ($i = 1; $i <= 6; $i++) {
            $this->createWidgetMedia($widget);
        }

        return $widget;
    }

    public function addSplitTwoBackgroundMedia(Layout $layout): void
    {
        if ($layout->getMedia('split-two-background')->isNotEmpty()) {
            return;
        }

        $this->createMedia($layout, collection: 'split-two-background');
    }

    /**
     * @param  Collection<int, Site>  $sites
     */
}
