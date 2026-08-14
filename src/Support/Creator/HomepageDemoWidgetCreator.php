<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use Capell\Core\Enums\ContainerWidthEnum;
use Capell\Core\Enums\ContentStructure;
use Capell\Core\Models\Blueprint;
use Capell\DemoKit\Filament\Configurators\Widgets\HomepageSectionWidgetConfigurator;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Support\HomepageDemoContent;
use Capell\DemoKit\Support\HomepageDemoImages;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Enums\WidgetTypeGroupEnum;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Override;

abstract class HomepageDemoWidgetCreator extends ModernDemoWidgetCreator
{
    public function createHomepageHeroCommandCenterWidget(): Widget
    {
        $widget = $this->createHomepageBladeWidget(
            key: 'capell-home-hero-command-center',
            name: 'Capell Homepage Command Center Hero',
        );

        return $this->withHomepageHeroLayout($this->withHomepageHeroSlides($this->withHomepageContent($this->withHomepageImageSource($widget))));
    }

    public function createHomepageProofStripWidget(): Widget
    {
        return $this->withHomepageContent($this->createHomepageBladeWidget(
            key: 'capell-home-proof-strip',
            name: 'Capell Homepage Proof Strip',
        ));
    }

    public function createHomepageDemoShowcaseWidget(): Widget
    {
        return $this->withHomepageContent($this->withHomepageImageSource($this->createHomepageBladeWidget(
            key: 'capell-home-demo-showcase',
            name: 'Capell Homepage Demo Showcase',
        )));
    }

    public function createHomepageDemoWidgetsCarouselWidget(): Widget
    {
        return $this->withHomepageContent($this->createHomepageBladeWidget(
            key: 'capell-home-demo-widgets-carousel',
            name: 'Capell Homepage Demo Widgets Carousel',
        ));
    }

    public function createHomepageMarketplaceWidget(): Widget
    {
        return $this->withHomepageContent($this->withHomepageImageSource($this->createHomepageBladeWidget(
            key: 'capell-extension-marketplace-showcase',
            name: 'Extension Marketplace Showcase',
        )));
    }

    public function createHomepageTechnicalPipelineWidget(): Widget
    {
        return $this->withHomepageContent($this->createHomepageBladeWidget(
            key: 'capell-home-technical-pipeline',
            name: 'Capell Homepage Technical Pipeline',
        ));
    }

    public function createHomepageRouteSplitWidget(): Widget
    {
        return $this->withHomepageContent($this->createHomepageBladeWidget(
            key: 'capell-home-route-split',
            name: 'Capell Homepage Route Split',
        ));
    }

    public function createHomepageFinalCtaWidget(): Widget
    {
        $widget = $this->createHomepageBladeWidget(
            key: 'capell-home-final-cta',
            name: 'Capell Homepage Final CTA',
        );

        return $this->withContainedFullBleedSectionLayout($this->withHomepageContent($widget));
    }

    #[Override]
    protected function homepageBladeWidgetType(): Blueprint
    {
        $widgetType = $this->typeModel::query()->updateOrCreate(
            [
                'type' => LayoutTypeEnum::Widget->value,
                'key' => 'homepage-section',
            ],
            [
                'name' => 'Homepage section',
                'group' => WidgetTypeGroupEnum::Content->value,
                'admin' => [
                    'type_configurator' => 'Widget',
                    'configurator' => HomepageSectionWidgetConfigurator::getKey(),
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

        return $widgetType instanceof Blueprint ? $widgetType : resolve(TypeCreator::class)->defaultWidgetType();
    }

    private function withHomepageImageSource(Widget $widget): Widget
    {
        $source = HomepageDemoImages::sourceForWidget($widget->key);

        if ($source === null) {
            return $widget;
        }

        $meta = is_array($widget->meta) ? $widget->meta : [];
        $meta['image_source'] = $source;

        $widget->forceFill(['meta' => $meta])->save();

        return $widget;
    }

    private function withHomepageHeroSlides(Widget $widget): Widget
    {
        $meta = is_array($widget->meta) ? $widget->meta : [];
        $meta['hero_slides'] = HomepageDemoImages::heroSlides();

        $widget->forceFill(['meta' => $meta])->save();

        return $widget;
    }

    private function withHomepageContent(Widget $widget): Widget
    {
        $meta = is_array($widget->meta) ? $widget->meta : [];
        $meta['content'] = HomepageDemoContent::mergeForWidget(
            $widget->key,
            isset($meta['content']) && is_array($meta['content']) ? $meta['content'] : [],
        );

        $widget->forceFill(['meta' => $meta])->save();

        return $widget;
    }

    private function withHomepageHeroLayout(Widget $widget): Widget
    {
        return $this->withContainedFullBleedSectionLayout($widget);
    }

    private function withContainedFullBleedSectionLayout(Widget $widget): Widget
    {
        $meta = is_array($widget->meta) ? $widget->meta : [];
        $meta['container'] = ContainerWidthEnum::Default->value;
        $meta['margin'] = ['none'];
        $meta['padding'] = ['none'];

        $widget->forceFill(['meta' => $meta])->save();

        return $widget;
    }
}
