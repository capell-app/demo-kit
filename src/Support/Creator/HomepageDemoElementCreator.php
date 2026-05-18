<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use Capell\LayoutBuilder\Models\Element;

abstract class HomepageDemoElementCreator extends ModernDemoElementCreator
{
    public function createHomepageHeroCommandCenterElement(): Element
    {
        return $this->createHomepageBladeElement(
            key: 'capell-home-hero-command-center',
            name: 'Capell Homepage Command Center Hero',
        );
    }

    public function createHomepageProofStripElement(): Element
    {
        return $this->createHomepageBladeElement(
            key: 'capell-home-proof-strip',
            name: 'Capell Homepage Proof Strip',
        );
    }

    public function createHomepageDemoShowcaseElement(): Element
    {
        return $this->createHomepageBladeElement(
            key: 'capell-home-demo-showcase',
            name: 'Capell Homepage Demo Showcase',
        );
    }

    public function createHomepageMarketplaceElement(): Element
    {
        return $this->createHomepageBladeElement(
            key: 'capell-extension-marketplace-showcase',
            name: 'Extension Marketplace Showcase',
        );
    }

    public function createHomepageTechnicalPipelineElement(): Element
    {
        return $this->createHomepageBladeElement(
            key: 'capell-home-technical-pipeline',
            name: 'Capell Homepage Technical Pipeline',
        );
    }

    public function createHomepageRouteSplitElement(): Element
    {
        return $this->createHomepageBladeElement(
            key: 'capell-home-route-split',
            name: 'Capell Homepage Route Split',
        );
    }

    public function createHomepageFinalCtaElement(): Element
    {
        return $this->createHomepageBladeElement(
            key: 'capell-home-final-cta',
            name: 'Capell Homepage Final CTA',
        );
    }
}
