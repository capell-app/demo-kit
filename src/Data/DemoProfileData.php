<?php

declare(strict_types=1);

namespace Capell\DemoKit\Data;

use Spatie\LaravelData\Data;

final class DemoProfileData extends Data
{
    /**
     * @param  array{sites: int, pages_per_site: array{0: int, 1: int}, languages_per_site: array{0: int, 1: int}, page_depth: array{0: int, 1: int}, media_per_page: array{0: int, 1: int}}  $counts
     * @param  list<string>  $showcaseBlockOrder
     * @param  array<string, int>  $blockAssetMinimums
     * @param  list<string>  $placeholderLabels
     */
    public function __construct(
        public readonly ?int $seed,
        public readonly array $counts,
        public readonly int $minimumBlockCount,
        public readonly int $minimumMediaCount,
        public readonly array $showcaseBlockOrder,
        public readonly array $blockAssetMinimums,
        public readonly array $placeholderLabels,
    ) {}

    public static function default(): self
    {
        return new self(
            seed: config('capell-demo-kit.seed'),
            counts: config('capell-demo-kit.counts'),
            minimumBlockCount: 7,
            minimumMediaCount: 8,
            showcaseBlockOrder: [
                'capell-home-hero-command-center',
                'capell-home-proof-strip',
                'capell-home-demo-showcase',
                'capell-extension-marketplace-showcase',
                'capell-home-technical-pipeline',
                'capell-home-route-split',
                'capell-home-final-cta',
            ],
            blockAssetMinimums: [],
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
