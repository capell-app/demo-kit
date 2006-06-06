<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Models\Site;
use Capell\DemoKit\Data\DemoKitScreenshotFixtureData;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Capell\DemoKit\Support\DemoKitScreenshotFixtureGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * @method static DemoKitScreenshotFixtureData run(string $state, string $attemptToken)
 */
final class RestoreDemoKitScreenshotFixtureAction
{
    use AsObject;

    public function handle(string $state, string $attemptToken): DemoKitScreenshotFixtureData
    {
        DemoKitScreenshotFixtureGuard::assertEnvironment();
        $state = DemoKitScreenshotFixtureGuard::state($state);
        $attemptToken = DemoKitScreenshotFixtureGuard::attemptToken($attemptToken);

        return DB::transaction(function () use ($state, $attemptToken): DemoKitScreenshotFixtureData {
            $sites = $this->fixtureSites($state, $attemptToken);
            $runs = $this->fixtureRuns($state, $attemptToken);

            foreach ($sites as $site) {
                $this->assertOwnership($this->siteMarker($site), $site->getKey(), $state, $attemptToken, 'site');
            }

            foreach ($runs as $run) {
                $this->assertOwnership($this->runMarker($run), $run->getKey(), $state, $attemptToken, 'generation-run');
            }

            foreach ($sites as $site) {
                $site->forceDelete();
            }

            foreach ($runs as $run) {
                $run->delete();
            }

            return new DemoKitScreenshotFixtureData(
                operation: 'restored',
                state: $state,
                attemptToken: $attemptToken,
                siteIds: array_values($sites->map(fn (Site $site): int => $this->integerKey($site))->values()->all()),
                generationRunIds: array_values($runs->map(fn (DemoKitGenerationRun $run): int => $this->integerKey($run))->values()->all()),
            );
        });
    }

    /**
     * @return Collection<int, Site>
     */
    private function fixtureSites(string $state, string $attemptToken): Collection
    {
        return Site::withTrashed()->get()->filter(fn (Site $site): bool => DemoKitScreenshotFixtureGuard::owns($this->siteMarker($site), $state, $attemptToken, 'site'))->values();
    }

    /**
     * @return Collection<int, DemoKitGenerationRun>
     */
    private function fixtureRuns(string $state, string $attemptToken): Collection
    {
        return DemoKitGenerationRun::query()->get()->filter(fn (DemoKitGenerationRun $run): bool => DemoKitScreenshotFixtureGuard::owns($this->runMarker($run), $state, $attemptToken, 'generation-run'))->values();
    }

    /**
     * @param  array{package: string, attempt_token: string, state: string, role: string, row_id: int}|null  $marker
     */
    private function assertOwnership(?array $marker, mixed $rowId, string $state, string $attemptToken, string $role): void
    {
        if (! is_int($rowId)
            || $marker === null
            || ! DemoKitScreenshotFixtureGuard::owns($marker, $state, $attemptToken, $role)
            || $marker['row_id'] !== $rowId) {
            throw new RuntimeException('Refusing to restore a Demo Kit screenshot row with an invalid ownership marker.');
        }
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

    private function integerKey(DemoKitGenerationRun|Site $model): int
    {
        $key = $model->getKey();
        throw_unless(is_int($key), RuntimeException::class, 'Demo Kit screenshot fixture rows require integer keys.');

        return $key;
    }
}
