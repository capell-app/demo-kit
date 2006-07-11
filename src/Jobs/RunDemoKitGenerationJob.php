<?php

declare(strict_types=1);

namespace Capell\DemoKit\Jobs;

use Capell\Core\Models\Site;
use Capell\DemoKit\Actions\InsertExampleSiteDataAction;
use Capell\DemoKit\Actions\RedactDemoKitErrorMessageAction;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

final class RunDemoKitGenerationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 1200;

    public function __construct(public readonly int $runId) {}

    public function uniqueId(): string
    {
        return 'generation';
    }

    public function handle(): void
    {
        $run = DemoKitGenerationRun::query()->find($this->runId);

        if (! $run instanceof DemoKitGenerationRun || ! in_array($run->status, [DemoKitGenerationRun::STATUS_QUEUED, DemoKitGenerationRun::STATUS_RUNNING], true)) {
            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => $run->started_at ?? now(),
            'error_message' => null,
        ]);

        try {
            InsertExampleSiteDataAction::run($run->parameters);

            $run->update([
                'status' => DemoKitGenerationRun::STATUS_COMPLETED,
                'created_content' => $this->createdContent($run),
                'finished_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            $this->markFailed($run, $throwable);

            throw $throwable;
        }
    }

    public function failed(?Throwable $throwable): void
    {
        $run = DemoKitGenerationRun::query()->find($this->runId);

        if ($run instanceof DemoKitGenerationRun && $run->finished_at === null) {
            $this->markFailed($run, $throwable);
        }
    }

    private function markFailed(DemoKitGenerationRun $run, ?Throwable $throwable): void
    {
        $run->update([
            'status' => DemoKitGenerationRun::STATUS_FAILED,
            'error_message' => Str::limit(
                $throwable instanceof Throwable
                    ? RedactDemoKitErrorMessageAction::run($throwable)
                    : (string) __('capell-demo-kit::actions.example_site_data_installation_failed'),
                2000,
                '',
            ),
            'finished_at' => now(),
        ]);
    }

    /** @return list<array{name: string, site_id: int|null, url: string}> */
    private function createdContent(DemoKitGenerationRun $run): array
    {
        $plannedSites = data_get($run->review, 'plan.sites', []);
        if (! is_array($plannedSites)) {
            return [];
        }

        $names = array_values(array_filter(array_map(
            static fn (mixed $site): string => is_array($site) && is_string($site['name'] ?? null) ? $site['name'] : '',
            $plannedSites,
        )));
        $sites = Site::query()->whereIn('name', $names)->get()->keyBy('name');

        return array_values(array_map(function (string $name) use ($sites): array {
            $site = $sites->get($name);

            return [
                'name' => $name,
                'site_id' => $site instanceof Site && is_int($site->getKey()) ? $site->getKey() : null,
                'url' => $site instanceof Site && is_int($site->getKey())
                    ? url('/admin/sites/' . $site->getKey() . '/edit')
                    : '',
            ];
        }, $names));
    }
}
