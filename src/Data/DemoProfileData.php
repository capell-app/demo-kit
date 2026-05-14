<?php

declare(strict_types=1);

namespace Capell\DemoKit\Data;

use Spatie\LaravelData\Data;

final class DemoProfileData extends Data
{
    /**
     * @param  array{sites: int, pages_per_site: array{0: int, 1: int}, languages_per_site: array{0: int, 1: int}, page_depth: array{0: int, 1: int}, media_per_page: array{0: int, 1: int}}  $counts
     * @param  list<string>  $showcaseWidgetOrder
     * @param  array<string, int>  $widgetAssetMinimums
     * @param  list<string>  $placeholderLabels
     */
    public function __construct(
        public readonly ?int $seed,
        public readonly array $counts,
        public readonly int $minimumWidgetCount,
        public readonly int $minimumMediaCount,
        public readonly array $showcaseWidgetOrder,
        public readonly array $widgetAssetMinimums,
        public readonly array $placeholderLabels,
    ) {}

    public static function default(): self
    {
        return new self(
            seed: config('capell-demo-kit.seed'),
            counts: config('capell-demo-kit.counts'),
            minimumWidgetCount: 10,
            minimumMediaCount: 8,
            showcaseWidgetOrder: [
                'ap-hero-banner',
                'modern-stats',
                'ap-card-grid',
                'modern-process-steps',
                'ap-feature-list',
                'modern-alternating-content',
                'ap-image-gallery',
                'modern-testimonials',
                'modern-faq',
                'ap-cta-section',
            ],
            widgetAssetMinimums: [
                'ap-card-grid' => 3,
                'ap-feature-list' => 4,
                'ap-image-gallery' => 6,
            ],
            placeholderLabels: [
                'AP Card Grid',
                'AP Feature List',
                'Lorem Ipsum',
                'Our Work',
                'Meet Our Team',
                'Client Logos',
            ],
        );
    }
}
