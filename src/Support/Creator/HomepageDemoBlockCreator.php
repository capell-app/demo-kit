<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use Capell\LayoutBuilder\Models\Block;

abstract class HomepageDemoBlockCreator extends ModernDemoBlockCreator
{
    public function createHomepageHeroCommandCenterBlock(): Block
    {
        return $this->createHomepageBladeBlock(
            key: 'capell-home-hero-command-center',
            name: 'Capell Homepage Command Center Hero',
        );
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
        return $this->createHomepageBladeBlock(
            key: 'capell-home-demo-showcase',
            name: 'Capell Homepage Demo Showcase',
        );
    }

    public function createHomepageMarketplaceBlock(): Block
    {
        return $this->createHomepageBladeBlock(
            key: 'capell-extension-marketplace-showcase',
            name: 'Extension Marketplace Showcase',
        );
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
}
