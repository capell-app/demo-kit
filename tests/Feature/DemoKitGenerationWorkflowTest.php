<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\DemoKit\Actions\QueueDemoKitGenerationAction;
use Capell\DemoKit\Filament\Pages\DemoKitPage;
use Capell\DemoKit\Jobs\RunDemoKitGenerationJob;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Tests\Fixtures\Commands\TrackingDemoCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

function demoKitGenerationRunId(DemoKitGenerationRun $run): int
{
    $runId = $run->getKey();
    throw_unless(is_int($runId), RuntimeException::class, 'Demo Kit generation run must have an integer key.');

    return $runId;
}

it('deduplicates generation requests and persists queued state before command work starts', function (): void {
    Queue::fake();

    $first = QueueDemoKitGenerationAction::run(['url' => 'https://example.test']);
    $second = QueueDemoKitGenerationAction::run(['url' => 'https://ignored.test']);

    expect($second->is($first))->toBeTrue()
        ->and($first->status)->toBe('queued')
        ->and($first->parameters)->toBe(['url' => 'https://example.test']);

    Queue::assertPushed(RunDemoKitGenerationJob::class, 1);
});

it('runs the demo command in the durable job and persists lifecycle timestamps', function (): void {
    Queue::fake();
    TrackingDemoCommand::reset();
    CapellCore::getPackage(DemoKitServiceProvider::$packageName)->demoCommand = 'test:queued-example-site-data';
    Artisan::registerCommand(new TrackingDemoCommand(
        'test:queued-example-site-data {--url=} {--user=} {--languages=*} {--sites=*} {--force}',
    ));

    $run = QueueDemoKitGenerationAction::run([
        'url' => 'https://example.test',
        'languages' => ['en', 'fr'],
    ]);

    (new RunDemoKitGenerationJob(demoKitGenerationRunId($run)))->handle();
    $run->refresh();

    expect($run->status)->toBe('succeeded')
        ->and($run->started_at)->not->toBeNull()
        ->and($run->finished_at)->not->toBeNull()
        ->and(TrackingDemoCommand::$executionOrder)->toBe(['test:queued-example-site-data']);
});

it('releases stale active runs instead of blocking generation forever', function (): void {
    Queue::fake();
    $staleRun = DemoKitGenerationRun::query()->create([
        'status' => 'running',
        'parameters' => [],
        'started_at' => now()->subHour(),
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    $replacement = QueueDemoKitGenerationAction::run(['url' => 'https://replacement.test']);

    expect($replacement->is($staleRun))->toBeFalse()
        ->and($staleRun->refresh()->status)->toBe('failed')
        ->and($staleRun->finished_at)->not->toBeNull()
        ->and($replacement->status)->toBe('queued');
});

it('persists a redacted terminal failure for the admin polling surface', function (): void {
    $run = DemoKitGenerationRun::query()->create([
        'status' => 'running',
        'parameters' => [],
        'started_at' => now(),
    ]);

    (new RunDemoKitGenerationJob(demoKitGenerationRunId($run)))->failed(
        new RuntimeException('Generation failed with token=secret-value'),
    );

    $run->refresh();

    expect($run->status)->toBe('failed')
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->error_message)->not->toContain('secret-value');
});

it('restores persisted generation state when the admin page mounts', function (): void {
    $run = DemoKitGenerationRun::query()->create([
        'status' => 'running',
        'parameters' => [],
        'started_at' => now(),
    ]);

    $page = new DemoKitPage;
    $page->mount();

    expect($page->generationRunId)->toBe($run->getKey())
        ->and($page->generationStatus)->toBe('running');
});
