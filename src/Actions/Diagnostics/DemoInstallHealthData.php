<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions\Diagnostics;

use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class DemoInstallHealthData extends Data
{
    /**
     * @param  Collection<int, DoctorCheckResultData>  $checks
     */
    public function __construct(
        public Collection $checks,
    ) {}

    public function passed(): bool
    {
        return $this->checks->every(fn (DoctorCheckResultData $check): bool => $check->passed);
    }
}
