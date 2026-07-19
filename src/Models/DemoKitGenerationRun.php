<?php

declare(strict_types=1);

namespace Capell\DemoKit\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property int $id
 * @property string $status
 * @property array<string, mixed> $parameters
 * @property string|null $error_message
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 */
final class DemoKitGenerationRun extends Model
{
    protected $table = 'capell_demo_kit_generation_runs';

    /** @var list<string> */
    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function requestedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'parameters' => 'encrypted:array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
