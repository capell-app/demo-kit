<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Site;
use Capell\DemoKit\Actions\BuildDemoGenerationReviewAction;
use Capell\DemoKit\Actions\ListDemoKitProvenanceSitesAction;
use Capell\DemoKit\Actions\MarkDemoSiteProvenanceAction;
use Capell\DemoKit\Actions\QueueDemoKitGenerationAction;
use Capell\DemoKit\Actions\ResetDemoKitProvenanceAction;
use Capell\DemoKit\Filament\Pages\DemoKitPage;
use Capell\DemoKit\Jobs\RunDemoKitGenerationJob;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Tests\Fixtures\Commands\TrackingDemoCommand;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function demoKitGenerationRunId(DemoKitGenerationRun $run): int
{
    $runId = $run->getKey();
    throw_unless(is_int($runId), RuntimeException::class, 'Demo Kit generation run must have an integer key.');

    return $runId;
}

function demoKitGenerationActor(): User
{
    Gate::before(fn (): bool => true);

    return User::factory()->create();
}

it('reviews the recommended profile and normalises custom options', function (): void {
    $actor = demoKitGenerationActor();

    $recommended = BuildDemoGenerationReviewAction::run([], $actor);
    $custom = BuildDemoGenerationReviewAction::run([
        'url' => 'https://demo.example.test',
        'sites' => 'Alpha, Beta, Alpha',
        'languages' => ['en', 'fr'],
        'pages' => 4,
        'seed' => 44,
    ], $actor);

    expect($recommended->options)->toHaveKey('seed')
        ->and($recommended->siteCount)->toBeGreaterThan(0)
        ->and($recommended->languageCount)->toBeGreaterThan(0)
        ->and($recommended->pageCount)->toBeGreaterThan(0)
        ->and($recommended->mediaCount)->toBeGreaterThanOrEqual(0)
        ->and($custom->options)->toBe([
            'url' => 'https://demo.example.test',
            'sites' => ['Alpha', 'Beta'],
            'languages' => ['en', 'fr'],
            'pages' => 4,
            'seed' => 44,
        ])
        ->and($custom->pageCount)->toBe(8)
        ->and(strlen($custom->fingerprint))->toBe(64);
});

it('invalidates a review when its deterministic options change', function (): void {
    $actor = demoKitGenerationActor();
    $first = BuildDemoGenerationReviewAction::run(['pages' => 3], $actor);
    $second = BuildDemoGenerationReviewAction::run(['pages' => 4], $actor);

    expect($first->fingerprint)->not->toBe($second->fingerprint);
});

it('shows ordinary collisions as blocking and Demo Kit sites as reusable', function (): void {
    $actor = demoKitGenerationActor();
    $ordinary = Site::factory()->create(['name' => 'Ordinary Site']);
    $demo = Site::factory()->create(['name' => 'Demo Site']);
    MarkDemoSiteProvenanceAction::run($demo);

    $ordinaryReview = BuildDemoGenerationReviewAction::run(['sites' => ['Ordinary Site'], 'pages' => 3], $actor);
    $demoReview = BuildDemoGenerationReviewAction::run(['sites' => ['Demo Site'], 'pages' => 3], $actor);

    expect($ordinaryReview->hasBlockingCollisions())->toBeTrue()
        ->and($ordinaryReview->collisions[0]['outcome'])->toBe('blocked')
        ->and($demoReview->hasBlockingCollisions())->toBeFalse()
        ->and($demoReview->collisions[0]['outcome'])->toBe('reuse')
        ->and($ordinary->exists)->toBeTrue();
});

it('requires permission, an unchanged review, and explicit confirmation before queueing', function (): void {
    $actor = demoKitGenerationActor();
    Queue::fake();
    $review = BuildDemoGenerationReviewAction::run(['sites' => ['Queue Site'], 'pages' => 3], $actor);

    expect(fn (): DemoKitGenerationRun => QueueDemoKitGenerationAction::run($review, $actor))
        ->toThrow(ValidationException::class, 'Explicit confirmation')
        ->and(fn (): DemoKitGenerationRun => QueueDemoKitGenerationAction::run($review, $actor, 'invalid', true))
        ->toThrow(ValidationException::class, 'plan changed');

    $run = QueueDemoKitGenerationAction::run($review, $actor, $review->fingerprint, true);

    /** @var array{counts: array{pages: int}} $runReview */
    $runReview = $run->review;

    expect($run->status)->toBe(DemoKitGenerationRun::STATUS_QUEUED)
        ->and($run->fingerprint)->toBe($review->fingerprint)
        ->and($runReview['counts']['pages'])->toBe(3);
    Queue::assertPushed(RunDemoKitGenerationJob::class, 1);
});

it('deduplicates the same reviewed request but rejects a different active request', function (): void {
    $actor = demoKitGenerationActor();
    Queue::fake();
    $firstReview = BuildDemoGenerationReviewAction::run(['sites' => ['Queue Site'], 'pages' => 3], $actor);
    $secondReview = BuildDemoGenerationReviewAction::run(['sites' => ['Other Site'], 'pages' => 3], $actor);
    $first = QueueDemoKitGenerationAction::run($firstReview, $actor, $firstReview->fingerprint, true);
    $duplicate = QueueDemoKitGenerationAction::run($firstReview, $actor, $firstReview->fingerprint, true);

    expect($duplicate->is($first))->toBeTrue()
        ->and(fn (): DemoKitGenerationRun => QueueDemoKitGenerationAction::run($secondReview, $actor, $secondReview->fingerprint, true))
        ->toThrow(ValidationException::class, 'already in progress');
    Queue::assertPushed(RunDemoKitGenerationJob::class, 1);
});

it('runs the demo command and persists the completed state with created-content links', function (): void {
    $actor = demoKitGenerationActor();
    Queue::fake();
    TrackingDemoCommand::reset();
    CapellCore::getPackage(DemoKitServiceProvider::$packageName)->demoCommand = 'test:queued-example-site-data';
    Artisan::registerCommand(new TrackingDemoCommand(
        'test:queued-example-site-data {--url=} {--user=} {--languages=*} {--sites=*} {--seed=} {--site-count=} {--page-count=} {--force}',
    ));
    $review = BuildDemoGenerationReviewAction::run(['sites' => ['Queue Site'], 'pages' => 3], $actor);
    $run = QueueDemoKitGenerationAction::run($review, $actor, $review->fingerprint, true);

    new RunDemoKitGenerationJob(demoKitGenerationRunId($run))->handle();
    $run->refresh();

    expect($run->status)->toBe(DemoKitGenerationRun::STATUS_COMPLETED)
        ->and($run->started_at)->not->toBeNull()
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->created_content)->toHaveCount(1)
        ->and($run->created_content[0]['name'])->toBe('Queue Site')
        ->and(TrackingDemoCommand::$executionOrder)->toBe(['test:queued-example-site-data']);
});

it('represents failed and stalled runs without leaking secrets', function (): void {
    $failed = DemoKitGenerationRun::query()->create([
        'status' => DemoKitGenerationRun::STATUS_RUNNING,
        'parameters' => [],
        'started_at' => now(),
    ]);

    new RunDemoKitGenerationJob(demoKitGenerationRunId($failed))->failed(
        new RuntimeException('Generation failed with token=secret-value'),
    );
    $failed->refresh();

    $stalled = DemoKitGenerationRun::query()->create([
        'status' => DemoKitGenerationRun::STATUS_RUNNING,
        'parameters' => [],
        'started_at' => now()->subHour(),
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);
    $actor = demoKitGenerationActor();
    Queue::fake();
    $review = BuildDemoGenerationReviewAction::run(['sites' => ['Replacement'], 'pages' => 3], $actor);
    QueueDemoKitGenerationAction::run($review, $actor, $review->fingerprint, true);
    $stalled->refresh();

    expect($failed->status)->toBe(DemoKitGenerationRun::STATUS_FAILED)
        ->and($failed->error_message)->not->toContain('secret-value')
        ->and($stalled->status)->toBe(DemoKitGenerationRun::STATUS_STALLED)
        ->and($stalled->finished_at)->not->toBeNull();
});

it('lists and resets only provenance-owned sites, never ordinary content', function (): void {
    $actor = demoKitGenerationActor();
    $demo = Site::factory()->create(['name' => 'Generated Site']);
    $ordinary = Site::factory()->create(['name' => 'Ordinary Site']);
    MarkDemoSiteProvenanceAction::run($demo);

    expect(ListDemoKitProvenanceSitesAction::run($actor)->pluck('name')->all())
        ->toContain('Generated Site')
        ->not->toContain('Ordinary Site');

    ResetDemoKitProvenanceAction::run(['Generated Site', 'Ordinary Site'], $actor);

    expect(Site::withTrashed()->whereKey($demo->getKey())->first()?->trashed())->toBeTrue()
        ->and(Site::query()->whereKey($ordinary->getKey())->exists())->toBeTrue();
});

it('denies reset and review outside local or testing environments', function (): void {
    $actor = demoKitGenerationActor();
    app()->detectEnvironment(static fn (): string => 'production');

    expect(fn (): mixed => BuildDemoGenerationReviewAction::run([], $actor))
        ->toThrow(ValidationException::class, 'local or testing')
        ->and(fn (): mixed => ResetDemoKitProvenanceAction::run([], $actor))
        ->toThrow(ValidationException::class, 'local or testing');

    app()->detectEnvironment(static fn (): string => 'testing');
});

it('restores every durable run state on the admin page', function (): void {
    foreach ([
        DemoKitGenerationRun::STATUS_QUEUED,
        DemoKitGenerationRun::STATUS_RUNNING,
        DemoKitGenerationRun::STATUS_COMPLETED,
        DemoKitGenerationRun::STATUS_FAILED,
        DemoKitGenerationRun::STATUS_STALLED,
    ] as $status) {
        $run = DemoKitGenerationRun::query()->create([
            'status' => $status,
            'parameters' => [],
            'review' => ['counts' => ['pages' => 0]],
            'fingerprint' => str_repeat('a', 64),
            'created_content' => [],
        ]);

        $page = new DemoKitPage;
        $page->mount();

        expect($page->generationStatus)->toBe($status)
            ->and($page->generationRunId)->toBe($run->getKey());
    }
});
