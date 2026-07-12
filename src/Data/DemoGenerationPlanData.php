<?php

declare(strict_types=1);

namespace Capell\DemoKit\Data;

use Spatie\LaravelData\Data;

final class DemoGenerationPlanData extends Data
{
    /**
     * @param  list<string>  $languageCodes
     * @param  list<DemoSiteGenerationPlanData>  $sites
     */
    public function __construct(
        public readonly ?int $seed,
        public readonly array $languageCodes,
        public readonly array $sites,
        public readonly DemoProfileData $profile,
    ) {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }
}
