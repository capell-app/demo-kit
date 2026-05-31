<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use Capell\Core\Enums\ContainerWidthEnum;
use Capell\Core\Enums\ContentStructure;
use Capell\Core\Models\Blueprint;
use Capell\DemoKit\Filament\Configurators\Blocks\HomepageSectionBlockConfigurator;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Support\HomepageDemoContent;
use Capell\LayoutBuilder\Enums\BlockTypeGroupEnum;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Override;

abstract class HomepageDemoBlockCreator extends ModernDemoBlockCreator
{
    private const array HOMEPAGE_IMAGE_SOURCES = [
        'capell-home-hero-command-center' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=720&q=75',
        'capell-home-demo-showcase' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=720&q=75',
        'capell-extension-marketplace-showcase' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=720&q=75',
    ];

    private const array HOMEPAGE_HERO_SLIDES = [
        [
            'image' => [
                'type' => 'url',
                'url' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=720&q=75',
            ],
            'alt' => 'Capell CMS workspace preview',
            'label' => 'Page types',
            'value' => 'Home, Resources, Services',
            'status' => 'Typed',
        ],
        [
            'image' => [
                'type' => 'url',
                'url' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=720&q=75',
            ],
            'alt' => 'Capell content package dashboard preview',
            'label' => 'Packages',
            'value' => 'Layout Builder, SEO, Search, Publishing',
            'status' => 'Installed',
        ],
        [
            'image' => [
                'type' => 'url',
                'url' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=720&q=75',
            ],
            'alt' => 'Capell publishing workflow preview',
            'label' => 'Workflow',
            'value' => 'Draft, preview, approve, publish',
            'status' => 'Traceable',
        ],
    ];

    public function createHomepageHeroCommandCenterBlock(): Widget
    {
        $block = $this->createHomepageBladeBlock(
            key: 'capell-home-hero-command-center',
            name: 'Capell Homepage Command Center Hero',
        );

        return $this->withHomepageHeroLayout($this->withHomepageHeroSlides($this->withHomepageContent($this->withHomepageImageSource($block))));
    }

    public function createHomepageProofStripBlock(): Widget
    {
        return $this->withHomepageContent($this->createHomepageBladeBlock(
            key: 'capell-home-proof-strip',
            name: 'Capell Homepage Proof Strip',
        ));
    }

    public function createHomepageDemoShowcaseBlock(): Widget
    {
        return $this->withHomepageContent($this->withHomepageImageSource($this->createHomepageBladeBlock(
            key: 'capell-home-demo-showcase',
            name: 'Capell Homepage Demo Showcase',
        )));
    }

    public function createHomepageDemoWidgetsCarouselBlock(): Widget
    {
        return $this->withHomepageContent($this->createHomepageBladeBlock(
            key: 'capell-home-demo-widgets-carousel',
            name: 'Capell Homepage Demo Widgets Carousel',
        ));
    }

    public function createHomepageMarketplaceBlock(): Widget
    {
        return $this->withHomepageContent($this->withHomepageImageSource($this->createHomepageBladeBlock(
            key: 'capell-extension-marketplace-showcase',
            name: 'Extension Marketplace Showcase',
        )));
    }

    public function createHomepageTechnicalPipelineBlock(): Widget
    {
        return $this->withHomepageContent($this->createHomepageBladeBlock(
            key: 'capell-home-technical-pipeline',
            name: 'Capell Homepage Technical Pipeline',
        ));
    }

    public function createHomepageRouteSplitBlock(): Widget
    {
        return $this->withHomepageContent($this->createHomepageBladeBlock(
            key: 'capell-home-route-split',
            name: 'Capell Homepage Route Split',
        ));
    }

    public function createHomepageFinalCtaBlock(): Widget
    {
        $block = $this->createHomepageBladeBlock(
            key: 'capell-home-final-cta',
            name: 'Capell Homepage Final CTA',
        );

        return $this->withContainedFullBleedSectionLayout($this->withHomepageContent($block));
    }

    #[Override]
    protected function homepageBladeBlockType(): Blueprint
    {
        $blockType = $this->typeModel::query()->updateOrCreate(
            [
                'type' => LayoutTypeEnum::Widget->value,
                'key' => 'homepage-section',
            ],
            [
                'name' => 'Homepage section',
                'group' => BlockTypeGroupEnum::Content->value,
                'admin' => [
                    'type_configurator' => 'Widget',
                    'configurator' => HomepageSectionBlockConfigurator::getKey(),
                    'icon' => 'heroicon-o-home',
                    'notes' => 'Demo homepage sections with editable content payloads.',
                ],
                'meta' => [
                    'component' => DemoKitServiceProvider::HomepageSectionRenderable,
                    'content_structure' => ContentStructure::Html,
                    'padding' => ['lg'],
                ],
                'status' => true,
            ],
        );

        return $blockType instanceof Blueprint ? $blockType : resolve(TypeCreator::class)->defaultBlockType();
    }

    private function withHomepageImageSource(Widget $block): Widget
    {
        $url = self::HOMEPAGE_IMAGE_SOURCES[$block->key] ?? null;

        if ($url === null) {
            return $block;
        }

        $meta = is_array($block->meta) ? $block->meta : [];
        $meta['image_source'] = [
            'type' => 'url',
            'url' => $url,
        ];

        $block->forceFill(['meta' => $meta])->save();

        return $block;
    }

    private function withHomepageHeroSlides(Widget $block): Widget
    {
        $meta = is_array($block->meta) ? $block->meta : [];
        $meta['hero_slides'] = self::HOMEPAGE_HERO_SLIDES;

        $block->forceFill(['meta' => $meta])->save();

        return $block;
    }

    private function withHomepageContent(Widget $block): Widget
    {
        $meta = is_array($block->meta) ? $block->meta : [];
        $meta['content'] = HomepageDemoContent::mergeForBlock(
            $block->key,
            isset($meta['content']) && is_array($meta['content']) ? $meta['content'] : [],
        );

        $block->forceFill(['meta' => $meta])->save();

        return $block;
    }

    private function withHomepageHeroLayout(Widget $block): Widget
    {
        return $this->withContainedFullBleedSectionLayout($block);
    }

    private function withContainedFullBleedSectionLayout(Widget $block): Widget
    {
        $meta = is_array($block->meta) ? $block->meta : [];
        $meta['container'] = ContainerWidthEnum::Default->value;
        $meta['margin'] = ['none'];
        $meta['padding'] = ['none'];

        $block->forceFill(['meta' => $meta])->save();

        return $block;
    }
}
