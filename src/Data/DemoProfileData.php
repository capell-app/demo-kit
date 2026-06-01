<?php

declare(strict_types=1);

namespace Capell\DemoKit\Data;

use Spatie\LaravelData\Data;

final class DemoProfileData extends Data
{
    /**
     * @param  array{sites: int, pages_per_site: array{0: int, 1: int}, languages_per_site: array{0: int, 1: int}, page_depth: array{0: int, 1: int}, media_per_page: array{0: int, 1: int}}  $counts
     * @param  list<string>  $homepageOpeningBlockKeys
     * @param  list<string>  $showcaseBlockOrder
     * @param  array<string, int>  $blockAssetMinimums
     * @param  list<string>  $placeholderLabels
     */
    public function __construct(
        public readonly ?int $seed,
        public readonly array $counts,
        public readonly int $minimumBlockCount,
        public readonly int $minimumMediaCount,
        public readonly array $homepageOpeningBlockKeys,
        public readonly array $showcaseBlockOrder,
        public readonly array $blockAssetMinimums,
        public readonly array $placeholderLabels,
    ) {}

    public static function default(): self
    {
        return new self(
            seed: config('capell-demo-kit.seed'),
            counts: config('capell-demo-kit.counts'),
            minimumBlockCount: (int) config('capell-demo-kit.health.minimum_block_count', 8),
            minimumMediaCount: (int) config('capell-demo-kit.health.minimum_media_count', 8),
            homepageOpeningBlockKeys: self::stringList(config('capell-demo-kit.health.homepage_opening_block_keys'), [
                'capell-home-hero-command-center',
            ]),
            showcaseBlockOrder: self::stringList(config('capell-demo-kit.health.showcase_block_order'), [
                'capell-home-hero-command-center',
                'capell-home-proof-strip',
                'capell-home-demo-showcase',
                'capell-home-demo-widgets-carousel',
                'capell-extension-marketplace-showcase',
                'capell-home-technical-pipeline',
                'capell-home-route-split',
                'capell-home-final-cta',
            ]),
            blockAssetMinimums: self::integerMap(config('capell-demo-kit.health.block_asset_minimums'), []),
            placeholderLabels: self::stringList(config('capell-demo-kit.health.placeholder_labels'), [
                'AP Card Grid',
                'AP Feature List',
                'Editorial Workflow',
                'Our Work',
                'Meet Our Team',
                'Client Logos',
            ]),
        );
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private static function stringList(mixed $value, array $default): array
    {
        if (! is_array($value)) {
            return $default;
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @param  array<string, int>  $default
     * @return array<string, int>
     */
    private static function integerMap(mixed $value, array $default): array
    {
        if (! is_array($value)) {
            return $default;
        }

        $map = [];

        foreach ($value as $key => $count) {
            if (! is_string($key) || ! is_int($count)) {
                continue;
            }

            $map[$key] = $count;
        }

        return $map;
    }
}
