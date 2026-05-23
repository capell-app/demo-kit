<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use Capell\LayoutBuilder\Models\Block;

abstract class HomepageDemoBlockCreator extends ModernDemoBlockCreator
{
    private const HOMEPAGE_IMAGE_SOURCES = [
        'capell-home-hero-command-center' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80',
        'capell-home-demo-showcase' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
        'capell-extension-marketplace-showcase' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80',
    ];

    public function createHomepageHeroCommandCenterBlock(): Block
    {
        return $this->withHomepageImageSource($this->createHomepageBladeBlock(
            key: 'capell-home-hero-command-center',
            name: 'Capell Homepage Command Center Hero',
        ));
    }

    public function createHomepageProofStripBlock(): Block
    {
        return $this->createHomepageBladeBlock(
            key: 'capell-home-proof-strip',
            name: 'Capell Homepage Proof Strip',
        );
    }

    public function createHomepageDemoShowcaseBlock(): Block
    {
        return $this->withHomepageImageSource($this->createHomepageBladeBlock(
            key: 'capell-home-demo-showcase',
            name: 'Capell Homepage Demo Showcase',
        ));
    }

    public function createHomepageDemoWidgetsCarouselBlock(): Block
    {
        return $this->createHomepageBladeBlock(
            key: 'capell-home-demo-widgets-carousel',
            name: 'Capell Homepage Demo Widgets Carousel',
        );
    }

    public function createHomepageMarketplaceBlock(): Block
    {
        return $this->withHomepageImageSource($this->createHomepageBladeBlock(
            key: 'capell-extension-marketplace-showcase',
            name: 'Extension Marketplace Showcase',
        ));
    }

    public function createHomepageTechnicalPipelineBlock(): Block
    {
        return $this->createHomepageBladeBlock(
            key: 'capell-home-technical-pipeline',
            name: 'Capell Homepage Technical Pipeline',
        );
    }

    public function createHomepageRouteSplitBlock(): Block
    {
        return $this->createHomepageBladeBlock(
            key: 'capell-home-route-split',
            name: 'Capell Homepage Route Split',
        );
    }

    public function createHomepageFinalCtaBlock(): Block
    {
        return $this->createHomepageBladeBlock(
            key: 'capell-home-final-cta',
            name: 'Capell Homepage Final CTA',
        );
    }

    private function withHomepageImageSource(Block $block): Block
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
}
