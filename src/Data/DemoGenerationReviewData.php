<?php

declare(strict_types=1);

namespace Capell\DemoKit\Data;

final readonly class DemoGenerationReviewData
{
    /**
     * @param  array<string, mixed>  $options
     * @param  list<array{name: string, site_id: int|null, provenance_owned: bool, outcome: string}>  $collisions
     */
    public function __construct(
        public DemoGenerationPlanData $plan,
        public array $options,
        public string $fingerprint,
        public int $siteCount,
        public int $languageCount,
        public int $pageCount,
        public int $mediaCount,
        public array $collisions,
    ) {}

    public function hasBlockingCollisions(): bool
    {
        return collect($this->collisions)->contains(
            static fn (array $collision): bool => $collision['provenance_owned'] === false,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'options' => $this->options,
            'plan' => $this->plan->toArray(),
            'fingerprint' => $this->fingerprint,
            'counts' => [
                'sites' => $this->siteCount,
                'languages' => $this->languageCount,
                'pages' => $this->pageCount,
                'media' => $this->mediaCount,
            ],
            'collisions' => $this->collisions,
        ];
    }
}
