<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\DemoKit\Jobs\RunDemoKitGenerationJob;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

final class QueueDemoKitGenerationAction
{
    use AsFake;
    use AsObject;

    /** @param array<string, mixed> $parameters */
    public function handle(array $parameters, ?Model $actor = null): DemoKitGenerationRun
    {
        $generationRun = Cache::lock('capell-demo-kit:generation-queue', 10)->block(5, function () use ($parameters, $actor): DemoKitGenerationRun {
            DemoKitGenerationRun::query()
                ->whereIn('status', ['queued', 'running'])
                ->where('updated_at', '<', now()->subMinutes(20))
                ->update([
                    'status' => 'failed',
                    'error_message' => (string) __('capell-demo-kit::actions.example_site_data_stalled'),
                    'finished_at' => now(),
                ]);

            $activeRun = DemoKitGenerationRun::query()
                ->whereIn('status', ['queued', 'running'])
                ->latest('id')
                ->first();

            if ($activeRun instanceof DemoKitGenerationRun) {
                return $activeRun;
            }

            $run = DemoKitGenerationRun::query()->create([
                'status' => 'queued',
                'parameters' => $parameters,
                'requested_by_type' => $actor?->getMorphClass(),
                'requested_by_id' => $actor?->getKey(),
            ]);

            $runId = $run->getKey();
            throw_unless(is_int($runId), RuntimeException::class, 'Demo Kit generation run must have an integer key.');

            RunDemoKitGenerationJob::dispatch($runId);

            return $run;
        });

        throw_unless($generationRun instanceof DemoKitGenerationRun, RuntimeException::class, 'Demo Kit generation could not be queued.');

        return $generationRun;
    }
}
