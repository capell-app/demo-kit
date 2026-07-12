<?php

declare(strict_types=1);

namespace Capell\DemoKit\Data;

use Spatie\LaravelData\Data;

final class DemoSiteGenerationPlanData extends Data
{
    /**
     * @param  list<string>  $languageCodes
     * @param  list<DemoPagePlanData>  $pages
     */
    public function __construct(
        public readonly string $name,
        public readonly array $languageCodes,
        public readonly array $pages,
    ) {}

    /**
     * @return array{name: array<string, string>, children: list<array<string, mixed>>}
     */
    public function toContentTree(): array
    {
        return [
            'name' => array_fill_keys($this->languageCodes, $this->name),
            'children' => array_map(
                static fn (DemoPagePlanData $page): array => $page->toContentTreeNode(),
                $this->pages,
            ),
        ];
    }

    public function pageCount(): int
    {
        return array_sum(array_map($this->countPage(...), $this->pages));
    }

    private function countPage(DemoPagePlanData $page): int
    {
        return 1 + array_sum(array_map($this->countPage(...), $page->children));
    }
}
