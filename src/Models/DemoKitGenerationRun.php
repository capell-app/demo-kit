<?php

declare(strict_types=1);

namespace Capell\DemoKit\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property int $id
 * @property string $status
 * @property array<string, mixed> $parameters
 * @property array<string, mixed>|null $review
 * @property array<int, array<string, mixed>> $created_content
 * @property string|null $fingerprint
 * @property string|null $error_message
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 */
final class DemoKitGenerationRun extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_RUNNING = 'running';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_STALLED = 'stalled';

    protected $table = 'capell_demo_kit_generation_runs';

    /** @var list<string> */
    protected $guarded = ['id'];

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
            'review' => 'encrypted:array',
            'created_content' => 'encrypted:array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
