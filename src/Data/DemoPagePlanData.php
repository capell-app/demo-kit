<?php

declare(strict_types=1);

namespace Capell\DemoKit\Data;

use Spatie\LaravelData\Data;

final class DemoPagePlanData extends Data
{
    /**
     * @param  array<string, string>  $name
     * @param  list<DemoPagePlanData>  $children
     */
    public function __construct(
        public readonly array $name,
        public readonly int $mediaCount = 1,
        public readonly array $children = [],
    ) {}

    /**
     * @return array{name: array<string, string>, media_count: int, children?: list<array<string, mixed>>}
     */
    public function toContentTreeNode(): array
    {
        $node = [
            'name' => $this->name,
            'media_count' => $this->mediaCount,
        ];

        if ($this->children !== []) {
            $node['children'] = array_map(
                static fn (DemoPagePlanData $page): array => $page->toContentTreeNode(),
                $this->children,
            );
        }

        return $node;
    }
}
