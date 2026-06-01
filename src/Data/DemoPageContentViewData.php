<?php

declare(strict_types=1);

namespace Capell\DemoKit\Data;

use Spatie\LaravelData\Data;

final class DemoPageContentViewData extends Data
{
    /**
     * @param  array<string, mixed>  $pageMeta
     * @param  list<array<string, mixed>>  $assetSections
     */
    public function __construct(
        public readonly string $pageName,
        public readonly string $pageSlug,
        public readonly array $pageMeta,
        public readonly bool $hasVisibleHero,
        public readonly ?string $content,
        public readonly ?string $contentStructure,
        public readonly int $occurrence,
        public readonly array $assetSections,
        public readonly bool $hasAssetSections,
        public readonly bool $isContactPage,
    ) {}
}
