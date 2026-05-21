<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Site;
use Capell\LayoutBuilder\Enums\BlockComponentEnum;
use Capell\LayoutBuilder\Enums\BlockTypeEnum;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Models\Block;
use Capell\LayoutBuilder\Support\Creator\BlockCreator;
use Illuminate\Database\Eloquent\Collection;

abstract class ApDemoBlockCreator extends HomepageDemoBlockCreator
{
    public function createApHeroBannerBlock(): Block
    {
        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
            ->firstWhere('key', BlockTypeEnum::HeroBanner)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
                ->firstWhere('key', BlockTypeEnum::Default);

        $block = $this->blockModel::query()->firstOrCreate(['key' => 'ap-hero-banner'], [
            'name' => 'AP Hero Banner',
            'blueprint_id' => $blockType->id,
            'meta' => [
                'component' => BlockComponentEnum::ApHeroBanner,
            ],
        ]);

        $block->forceFill([
            'name' => 'Capell Product Hero',
            'blueprint_id' => $blockType->id,
            'meta' => [
                'component' => BlockComponentEnum::ApHeroBanner,
                'primary_button_text' => 'Explore the demo',
                'primary_button_url' => '/admin',
                'secondary_button_text' => 'Read the docs',
                'secondary_button_url' => '/docs/installation',
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $block->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Capell CMS',
                    'content' => '<p>The Laravel and Filament CMS operating system for multi-site publishing, visual layout building, package-owned frontends, and static-fast delivery.</p>',
                ],
            );
        }

        $this->createMedia($block, 'sharks', collection: MediaCollectionEnum::BackgroundImage);

        return $block;
    }

    public function createApCardGridBlock(): Block
    {
        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
            ->firstWhere('key', BlockTypeEnum::CardGrid)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
                ->firstWhere('key', BlockTypeEnum::Default);

        $block = $this->blockModel::query()->firstOrCreate(['key' => 'ap-card-grid'], [
            'name' => 'Capell Capability Cards',
            'blueprint_id' => $blockType->id,
            'meta' => [
                'component' => BlockComponentEnum::ApCardGrid,
            ],
        ]);

        $block->forceFill([
            'name' => 'Capell Capability Cards',
            'blueprint_id' => $blockType->id,
            'meta' => [
                'component' => BlockComponentEnum::ApCardGrid,
                'columns' => 3,
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $block->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'A complete CMS foundation, not a theme demo',
                    'content' => '<p>Capell brings the content model, admin workflow, frontend runtime, and release checks together so teams can ship production sites without stitching every layer by hand.</p>',
                ],
            );
        }

        $block->assets()->delete();

        $cards = [
            ['icon' => 'heroicon-o-circle-stack', 'title' => 'Structured content engine', 'description' => 'Model pages, sections, blocks, media, translations, and relationships with clear Laravel records instead of hardcoded templates.', 'link_text' => 'Inspect the model', 'link_url' => '/admin'],
            ['icon' => 'heroicon-o-rectangle-group', 'title' => 'Visual layout builder', 'description' => 'Compose real frontend sections from editable blocks while keeping rendering package-owned and predictable.', 'link_text' => 'Edit the homepage', 'link_url' => '/admin'],
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

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $card['title'], 'content' => sprintf('<p>%s</p>', $card['description'])],
                );
            }

            $block->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $block;
    }

    public function createApFeatureListBlock(): Block
    {
        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
            ->firstWhere('key', BlockTypeEnum::FeatureList)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
                ->firstWhere('key', BlockTypeEnum::Default);

        $block = $this->blockModel::query()->firstOrCreate(['key' => 'ap-feature-list'], [
            'name' => 'Capell Workflow Feature List',
            'blueprint_id' => $blockType->id,
            'meta' => [
                'component' => BlockComponentEnum::ApFeatureList,
            ],
        ]);

        $block->forceFill([
            'name' => 'Capell Workflow Feature List',
            'blueprint_id' => $blockType->id,
            'meta' => [
                'component' => BlockComponentEnum::ApFeatureList,
                'layout' => 'grid',
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $block->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Everything visible is backed by editable records',
                    'content' => '<p>The default homepage is deliberately assembled from Capell blocks, assets, media, and translations so the admin experience proves the frontend is not a static mockup.</p>',
                ],
            );
        }

        $block->assets()->delete();

        $features = [
            ['icon' => 'heroicon-o-language', 'title' => 'Page translations', 'description' => 'Hero titles, body copy, SEO fields, and language variants live in translation records.'],
            ['icon' => 'heroicon-o-photo', 'title' => 'Media-driven surfaces', 'description' => 'Hero backgrounds, gallery items, cards, and section imagery resolve through Capell media records.'],
            ['icon' => 'heroicon-o-pencil-square', 'title' => 'Editor-owned sections', 'description' => 'Homepage cards, feature rows, FAQs, testimonials, and CTAs are all admin-managed content.'],
            ['icon' => 'heroicon-o-shield-check', 'title' => 'Release diagnostics', 'description' => 'Doctor checks verify the demo, homepage, blocks, runtime manifests, and generated frontend CSS.'],
        ];

        foreach ($features as $feature) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $feature['title']], [
                'meta' => ['icon' => $feature['icon']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $feature['title'], 'content' => sprintf('<p>%s</p>', $feature['description'])],
                );
            }

            $block->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $block;
    }

    public function createFeatureListBlock(): Block
    {
        $block = resolve(BlockCreator::class)->featuresBlock();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $block->translations()->firstOrCreate(
                ['language_id' => $language->id],
                ['title' => 'Features'],
            );
        }

        if ($block->assets()->exists()) {
            return $block;
        }

        $features = [
            ['icon' => 'heroicon-o-light-bulb', 'title' => 'Reusable CMS Patterns', 'description' => 'We use Laravel packages, Filament resources, and reusable blocks to keep CMS implementations maintainable.'],
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

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->firstOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $feature['title'], 'content' => sprintf('<p>%s</p>', $feature['description'])],
                );
            }

            $block->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $block;
    }

    public function createApCtaSectionBlock(): Block
    {
        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
            ->firstWhere('key', BlockTypeEnum::CTASection)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
                ->firstWhere('key', BlockTypeEnum::Default);

        $block = $this->blockModel::query()->firstOrCreate(['key' => 'ap-cta-section'], [
            'name' => 'AP CTA Section',
            'blueprint_id' => $blockType->id,
            'meta' => [
                'component' => BlockComponentEnum::ApCTASection,
            ],
        ]);

        $block->forceFill([
            'name' => 'Capell Showcase CTA',
            'blueprint_id' => $blockType->id,
            'meta' => [
                'component' => BlockComponentEnum::ApCTASection,
                'primary_button_text' => 'Open the admin',
                'primary_button_url' => '/admin',
                'secondary_button_text' => 'Run install doctor',
                'secondary_button_url' => '/docs/installation',
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $block->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'A demo site that proves the CMS stack is wired',
                    'content' => '<p>Change the homepage in Filament, regenerate the frontend, and use Capell doctor to confirm content, assets, runtime JavaScript, and layouts are all healthy.</p>',
                ],
            );
        }

        return $block;
    }

    public function createApImageGalleryBlock(): Block
    {
        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
            ->firstWhere('key', BlockTypeEnum::ImageGallery)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
                ->firstWhere('key', BlockTypeEnum::Default);

        $block = $this->blockModel::query()->firstOrCreate(['key' => 'ap-image-gallery'], [
            'name' => 'AP Image Gallery',
            'blueprint_id' => $blockType->id,
            'meta' => [
                'component' => BlockComponentEnum::ApImageGallery,
            ],
        ]);

        $block->forceFill([
            'name' => 'Capell Media Gallery',
            'blueprint_id' => $blockType->id,
            'meta' => [
                'component' => BlockComponentEnum::ApImageGallery,
                'layout' => 'grid',
                'columns' => 3,
                'lightbox' => true,
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $block->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Media that stays editable',
                    'content' => '<p>Use the gallery to verify image records, captions, crops, and frontend rendering stay connected from admin to public page.</p>',
                ],
            );
        }

        if ($block->assets()->exists()) {
            return $block;
        }

        for ($i = 1; $i <= 6; $i++) {
            $this->createBlockMedia($block);
        }

        return $block;
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
