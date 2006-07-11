<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\DemoKit\Models\DemoKitGenerationRun;
use Lorisleiva\Actions\Concerns\AsObject;

final class ReclaimStalledDemoKitGenerationRunsAction
{
    use AsObject;

    public function handle(?int $runId = null): void
    {
        $query = DemoKitGenerationRun::query();

        if ($runId !== null) {
            $query->whereKey($runId);
        }

        $query
            ->whereIn('status', [DemoKitGenerationRun::STATUS_QUEUED, DemoKitGenerationRun::STATUS_RUNNING])
            // Allow the worker's fifteen-minute timeout to elapse before reclaiming it.
            ->where('updated_at', '<', now()->subMinutes(20))
            ->update([
                'status' => DemoKitGenerationRun::STATUS_STALLED,
                'error_message' => (string) __('capell-demo-kit::actions.example_site_data_stalled'),
                'finished_at' => now(),
            ]);
    }
}
