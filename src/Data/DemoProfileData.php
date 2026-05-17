<?php

declare(strict_types=1);

namespace Capell\DemoKit\Data;

use Spatie\LaravelData\Data;

final class DemoProfileData extends Data
{
    /**
     * @param  array{sites: int, pages_per_site: array{0: int, 1: int}, languages_per_site: array{0: int, 1: int}, page_depth: array{0: int, 1: int}, media_per_page: array{0: int, 1: int}}  $counts
     * @param  list<string>  $showcaseElementOrder
     * @param  array<string, int>  $elementAssetMinimums
     * @param  list<string>  $placeholderLabels
     */
    public function __construct(
        public readonly ?int $seed,
        public readonly array $counts,
        public readonly int $minimumElementCount,
        public readonly int $minimumMediaCount,
        public readonly array $showcaseElementOrder,
        public readonly array $elementAssetMinimums,
        public readonly array $placeholderLabels,
    ) {}

    public static function default(): self
    {
        return new self(
            seed: config('capell-demo-kit.seed'),
            counts: config('capell-demo-kit.counts'),
            minimumElementCount: 7,
            minimumMediaCount: 8,
            showcaseElementOrder: [
                'capell-home-hero-command-center',
                'capell-home-proof-strip',
                'capell-home-demo-showcase',
                'capell-extension-marketplace-showcase',
                'capell-home-technical-pipeline',
                'capell-home-route-split',
                'capell-home-final-cta',
            ],
            elementAssetMinimums: [],
            placeholderLabels: [
                'AP Card Grid',
                'AP Feature List',
                'Editorial Workflow',
                'Our Work',
                'Meet Our Team',
                'Client Logos',
            ],
        );
    }
}
