<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Actions\CreateSiteAction;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\DemoKit\Data\DemoGenerationReviewData;
use Capell\DemoKit\Data\DemoKitScreenshotFixtureData;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Capell\DemoKit\Support\DemoKitPermissions;
use Capell\DemoKit\Support\DemoKitScreenshotFixtureGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * @method static DemoKitScreenshotFixtureData run(string $state, string $attemptToken, ?Model $actor = null)
 */
final class PrepareDemoKitScreenshotFixtureAction
{
    use AsObject;

    /**
     * @var array<string, array{sites: int, runs: int}>
     */
    private const array EXPECTED_ROWS = [
        'reuse-review' => ['sites' => 1, 'runs' => 0],
        'blocked-review' => ['sites' => 1, 'runs' => 0],
        'queue-confirmation' => ['sites' => 0, 'runs' => 0],
        'queued' => ['sites' => 0, 'runs' => 1],
        'running' => ['sites' => 0, 'runs' => 1],
        'completed' => ['sites' => 1, 'runs' => 1],
        'failed' => ['sites' => 0, 'runs' => 1],
        'stalled' => ['sites' => 0, 'runs' => 1],
        'reset-confirmation' => ['sites' => 2, 'runs' => 0],
        'reset-completed' => ['sites' => 2, 'runs' => 0],
    ];

    public function handle(string $state, string $attemptToken, ?Model $actor = null): DemoKitScreenshotFixtureData
    {
        DemoKitScreenshotFixtureGuard::assertEnvironment();
        $state = DemoKitScreenshotFixtureGuard::state($state);
        $attemptToken = DemoKitScreenshotFixtureGuard::attemptToken($attemptToken);

        return DB::transaction(function () use ($state, $attemptToken, $actor): DemoKitScreenshotFixtureData {
            $sites = $this->fixtureSites($state, $attemptToken);
            $runs = $this->fixtureRuns($state, $attemptToken);

            if ($sites->isNotEmpty() || $runs->isNotEmpty()) {
                $this->assertExpectedRows($state, $sites, $runs);
                $this->assertNoUnownedRuns($state, $attemptToken);

                foreach ($runs as $run) {
                    if (in_array($run->status, [DemoKitGenerationRun::STATUS_QUEUED, DemoKitGenerationRun::STATUS_RUNNING], true)) {
                        $run->forceFill(['updated_at' => now()])->saveQuietly();
                    }
                }

                return $this->data('prepared', $state, $attemptToken, $sites, $runs);
            }

            $this->assertNoUnownedRuns($state, $attemptToken);

            $resolvedActor = $this->resolveActor($actor);

            return match ($state) {
                'reuse-review', 'blocked-review' => $this->prepareCollisionReview($state, $attemptToken, $resolvedActor),
                'queue-confirmation' => $this->prepareQueueConfirmation($state, $attemptToken, $resolvedActor),
                'queued', 'running', 'completed', 'failed', 'stalled' => $this->prepareGenerationRun($state, $attemptToken, $resolvedActor),
                'reset-confirmation', 'reset-completed' => $this->prepareResetState($state, $attemptToken, $resolvedActor),
                default => throw new RuntimeException(sprintf('Unsupported Demo Kit screenshot fixture state [%s].', $state)),
            };
        });
    }

    private function prepareCollisionReview(string $state, string $attemptToken, Model $actor): DemoKitScreenshotFixtureData
    {
        $review = BuildDemoGenerationReviewAction::run([], $actor);
        $siteName = $review->plan->sites[0]->name;

        $site = $this->createFixtureSite(
            $siteName,
            $state,
            $attemptToken,
            $state === 'reuse-review',
        );

        $checkedReview = BuildDemoGenerationReviewAction::run([], $actor);
        $collision = collect($checkedReview->collisions)->firstWhere('name', $siteName);
        $expectedOutcome = $state === 'reuse-review' ? 'reuse' : 'blocked';

        throw_unless(
            is_array($collision) && $collision['outcome'] === $expectedOutcome,
            RuntimeException::class,
            sprintf('Demo Kit screenshot fixture did not produce the expected [%s] collision.', $expectedOutcome),
        );

        return $this->data('prepared', $state, $attemptToken, collect([$site]), collect());
    }

    private function prepareQueueConfirmation(string $state, string $attemptToken, Model $actor): DemoKitScreenshotFixtureData
    {
        $review = BuildDemoGenerationReviewAction::run([], $actor);

        throw_if(
            $review->hasBlockingCollisions(),
            RuntimeException::class,
            'Demo Kit screenshot queue confirmation requires an app without blocking collisions.',
        );

        return $this->data('prepared', $state, $attemptToken, collect(), collect());
    }

    private function prepareGenerationRun(string $state, string $attemptToken, Model $actor): DemoKitScreenshotFixtureData
    {
        $review = BuildDemoGenerationReviewAction::run($this->generationOptions($state), $actor);
        $site = null;

        if ($state === 'completed') {
            $site = $this->createFixtureSite(
                $review->plan->sites[0]->name,
                $state,
                $attemptToken,
                true,
            );
        }

        $run = $this->createFixtureRun($state, $attemptToken, $review, $actor, $site);

        return $this->data(
            'prepared',
            $state,
            $attemptToken,
            $site instanceof Site ? collect([$site]) : collect(),
            collect([$run]),
        );
    }

    private function prepareResetState(string $state, string $attemptToken, Model $actor): DemoKitScreenshotFixtureData
    {
        $provenanceSite = $this->createFixtureSite(
            $this->resetSiteName($state),
            $state,
            $attemptToken,
            true,
        );
        $ordinarySite = $this->createFixtureSite(
            'Demo Kit Screenshot Ordinary Content',
            $state,
            $attemptToken,
            false,
        );

        if ($state === 'reset-completed') {
            ResetDemoKitProvenanceAction::run([$provenanceSite->name], $actor);
        }

        return $this->data('prepared', $state, $attemptToken, collect([$provenanceSite, $ordinarySite]), collect());
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @param  Collection<int, DemoKitGenerationRun>  $runs
     */
    private function data(string $operation, string $state, string $attemptToken, Collection $sites, Collection $runs): DemoKitScreenshotFixtureData
    {
        return new DemoKitScreenshotFixtureData(
            operation: $operation,
            state: $state,
            attemptToken: $attemptToken,
            siteIds: array_values($sites->map(fn (Site $site): int => $this->integerKey($site))->values()->all()),
            generationRunIds: array_values($runs->map(fn (DemoKitGenerationRun $run): int => $this->integerKey($run))->values()->all()),
        );
    }

    /**
     * @return Collection<int, Site>
     */
    private function fixtureSites(string $state, string $attemptToken): Collection
    {
        return Site::withTrashed()->get()->filter(fn (Site $site): bool => DemoKitScreenshotFixtureGuard::owns(
            $this->siteMarker($site),
            $state,
            $attemptToken,
            'site',
        ))->values();
    }

    /**
     * @return Collection<int, DemoKitGenerationRun>
     */
    private function fixtureRuns(string $state, string $attemptToken): Collection
    {
        return DemoKitGenerationRun::query()->get()->filter(fn (DemoKitGenerationRun $run): bool => DemoKitScreenshotFixtureGuard::owns(
            $this->runMarker($run),
            $state,
            $attemptToken,
            'generation-run',
        ))->values();
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @param  Collection<int, DemoKitGenerationRun>  $runs
     */
    private function assertExpectedRows(string $state, Collection $sites, Collection $runs): void
    {
        $expected = self::EXPECTED_ROWS[$state];

        throw_unless(
            $sites->count() === $expected['sites'] && $runs->count() === $expected['runs'],
            RuntimeException::class,
            sprintf('Demo Kit screenshot fixture state [%s] has an incomplete owned row set.', $state),
        );

        foreach ($sites as $site) {
            $marker = $this->siteMarker($site);

            throw_unless(
                is_array($marker) && $marker['row_id'] === $this->integerKey($site),
                RuntimeException::class,
                'Demo Kit screenshot fixture site ownership marker is inconsistent.',
            );
        }

        foreach ($runs as $run) {
            $marker = $this->runMarker($run);

            throw_unless(
                is_array($marker) && $marker['row_id'] === $this->integerKey($run),
                RuntimeException::class,
                'Demo Kit screenshot fixture run ownership marker is inconsistent.',
            );
        }
    }

    private function assertNoUnownedRuns(string $state, string $attemptToken): void
    {
        foreach (DemoKitGenerationRun::query()->get() as $run) {
            if (DemoKitScreenshotFixtureGuard::owns($this->runMarker($run), $state, $attemptToken, 'generation-run')) {
                continue;
            }

            throw new RuntimeException(
                'Refusing to prepare a Demo Kit screenshot fixture while an unowned generation run exists.',
            );
        }
    }

    private function createFixtureSite(string $name, string $state, string $attemptToken, bool $provenanceOwned): Site
    {
        throw_if(
            Site::withTrashed()->where('name', $name)->exists(),
            RuntimeException::class,
            sprintf('Refusing to claim existing site [%s] for a screenshot fixture.', $name),
        );

        $language = Language::query()->where('code', 'en')->first();

        throw_unless($language instanceof Language, RuntimeException::class, 'Demo Kit screenshot fixtures require an English language.');

        $baseUrl = config('app.url');
        throw_unless(is_string($baseUrl) && $baseUrl !== '', RuntimeException::class, 'Demo Kit screenshot fixtures require an application URL.');

        $site = $provenanceOwned
            ? CreateDemoSiteAction::run($name, $this->fixtureUrl($baseUrl, $state, $attemptToken, $name), $language, collect([$language]))
            : CreateSiteAction::run($name, $this->fixtureUrl($baseUrl, $state, $attemptToken, $name), $language, collect([$language]));

        throw_unless($site->wasRecentlyCreated, RuntimeException::class, sprintf('Refusing to mark existing site [%s] as a fixture.', $name));

        return $this->markSite($site, $state, $attemptToken);
    }

    private function fixtureUrl(string $baseUrl, string $state, string $attemptToken, string $name): string
    {
        $identity = substr(hash('sha256', $state . "\0" . $attemptToken . "\0" . $name), 0, 24);

        return rtrim($baseUrl, '/') . '/__capell_screenshot_fixture/' . $identity;
    }

    private function markSite(Site $site, string $state, string $attemptToken): Site
    {
        $siteId = $this->integerKey($site);
        $meta = $site->getAttribute('meta');
        $meta = is_array($meta) ? $meta : [];

        $existingMarkerValue = data_get($meta, 'demo_kit.' . DemoKitScreenshotFixtureGuard::MARKER_KEY);
        $existingMarker = DemoKitScreenshotFixtureGuard::readMarker($existingMarkerValue);
        $marker = DemoKitScreenshotFixtureGuard::marker($state, $attemptToken, 'site', $siteId);

        throw_if(
            $existingMarkerValue !== null && ($existingMarker === null || $existingMarker !== $marker),
            RuntimeException::class,
            'Refusing to overwrite an existing Demo Kit screenshot fixture marker.',
        );

        data_set($meta, 'demo_kit.' . DemoKitScreenshotFixtureGuard::MARKER_KEY, $marker);
        $site->forceFill(['meta' => $meta])->saveQuietly();

        return $site->refresh();
    }

    private function createFixtureRun(
        string $state,
        string $attemptToken,
        DemoGenerationReviewData $review,
        Model $actor,
        ?Site $site,
    ): DemoKitGenerationRun {
        $now = CarbonImmutable::now();
        $run = new DemoKitGenerationRun;
        $run->status = $state;
        $run->parameters = [
            ...$review->options,
            'screenshot_fixture' => [
                'package' => DemoKitScreenshotFixtureGuard::PACKAGE,
                'attempt_token' => $attemptToken,
                'state' => $state,
                'role' => 'generation-run',
                'row_id' => 0,
            ],
        ];
        $run->review = $review->toArray();
        $run->fingerprint = $review->fingerprint;
        $run->created_content = $site instanceof Site ? [[
            'site_id' => $this->integerKey($site),
            'name' => $site->name,
            'url' => url('/admin/sites/' . $this->integerKey($site) . '/edit'),
        ]] : [];

        if ($state === DemoKitGenerationRun::STATUS_FAILED) {
            $run->error_message = RedactDemoKitErrorMessageAction::run('Fixture generation failed with token=' . $attemptToken);
        } elseif ($state === DemoKitGenerationRun::STATUS_STALLED) {
            $run->error_message = (string) __('capell-demo-kit::actions.example_site_data_stalled');
        }

        if (in_array($state, [DemoKitGenerationRun::STATUS_RUNNING, DemoKitGenerationRun::STATUS_COMPLETED, DemoKitGenerationRun::STATUS_FAILED, DemoKitGenerationRun::STATUS_STALLED], true)) {
            $run->started_at = $now->copy()->subMinutes(3);
        }

        if (in_array($state, [DemoKitGenerationRun::STATUS_COMPLETED, DemoKitGenerationRun::STATUS_FAILED, DemoKitGenerationRun::STATUS_STALLED], true)) {
            $run->finished_at = $now->copy()->subMinute();
        }

        if ($actor->getKey() !== null) {
            $run->requestedBy()->associate($actor);
        }

        $run->save();

        $runId = $this->integerKey($run);
        $parameters = $run->parameters;
        $parameters['screenshot_fixture'] = DemoKitScreenshotFixtureGuard::marker($state, $attemptToken, 'generation-run', $runId);
        $run->forceFill(['parameters' => $parameters, 'updated_at' => $now])->saveQuietly();

        return $run->refresh();
    }

    /**
     * @return array{sites: list<string>, languages: list<string>, pages: int, seed: int}
     */
    private function generationOptions(string $state): array
    {
        return [
            'sites' => [$this->generationSiteName($state)],
            'languages' => ['en'],
            'pages' => 3,
            'seed' => 3080308,
        ];
    }

    private function generationSiteName(string $state): string
    {
        return 'Demo Kit Screenshot ' . ucfirst($state) . ' Site';
    }

    private function resetSiteName(string $state): string
    {
        return 'Demo Kit Screenshot ' . ($state === 'reset-completed' ? 'Reset Completed' : 'Reset Confirmation') . ' Site';
    }

    private function resolveActor(?Model $actor): Model
    {
        if ($actor instanceof Model) {
            DemoKitPermissions::authorize($actor instanceof Authenticatable ? $actor : null);

            return $actor;
        }

        $authenticated = auth()->user();
        if ($authenticated instanceof Model) {
            DemoKitPermissions::authorize($authenticated instanceof Authenticatable ? $authenticated : null);

            return $authenticated;
        }

        $userModel = config('auth.providers.users.model');
        throw_unless(is_string($userModel) && is_a($userModel, Model::class, true), RuntimeException::class, 'Demo Kit screenshot fixtures require a seeded administrator actor.');

        /** @var class-string<Model> $userModel */
        $candidate = $userModel::query()->get()->first(
            static fn (mixed $user): bool => $user instanceof Model
                && $user instanceof Authenticatable
                && DemoKitPermissions::canManage($user),
        );

        throw_unless($candidate instanceof Model, RuntimeException::class, 'Demo Kit screenshot fixtures require a seeded administrator with extension-management permission.');

        return $candidate;
    }

    /**
     * @return array{package: string, attempt_token: string, state: string, role: string, row_id: int}|null
     */
    private function siteMarker(Site $site): ?array
    {
        return DemoKitScreenshotFixtureGuard::readMarker(
            data_get($site->getAttribute('meta'), 'demo_kit.' . DemoKitScreenshotFixtureGuard::MARKER_KEY),
        );
    }

    /**
     * @return array{package: string, attempt_token: string, state: string, role: string, row_id: int}|null
     */
    private function runMarker(DemoKitGenerationRun $run): ?array
    {
        return DemoKitScreenshotFixtureGuard::readMarker(
            data_get($run->getAttribute('parameters'), DemoKitScreenshotFixtureGuard::MARKER_KEY),
        );
    }

    private function integerKey(Model $model): int
    {
        $key = $model->getKey();
        throw_unless(is_int($key), RuntimeException::class, 'Demo Kit screenshot fixture rows require integer keys.');

        return $key;
    }
}
