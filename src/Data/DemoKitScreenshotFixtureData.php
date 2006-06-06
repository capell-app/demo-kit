<?php

declare(strict_types=1);

namespace Capell\DemoKit\Data;

final readonly class DemoKitScreenshotFixtureData
{
    /**
     * @param  list<int>  $siteIds
     * @param  list<int>  $generationRunIds
     */
    public function __construct(
        public string $operation,
        public string $state,
        public string $attemptToken,
        public array $siteIds,
        public array $generationRunIds,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'state' => $this->state,
            'attempt_token_sha256' => hash('sha256', $this->attemptToken),
            'rows' => [
                'sites' => $this->siteIds,
                'generation_runs' => $this->generationRunIds,
            ],
        ];
    }
}
