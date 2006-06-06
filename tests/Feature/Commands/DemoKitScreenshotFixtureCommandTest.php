<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\DemoKit\Actions\PrepareDemoKitScreenshotFixtureAction;
use Capell\DemoKit\Actions\RestoreDemoKitScreenshotFixtureAction;
use Capell\DemoKit\Console\Commands\DemoKitScreenshotFixtureCommand;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

function withDemoKitScreenshotEnvironment(Closure $callback): void
{
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());
    putenv('APP_ENV=testing');

    try {
        $callback();
    } finally {
        putenv('CAPELL_SCREENSHOT_FIXTURE');
        putenv('CAPELL_SCREENSHOT_APP_PATH');
        putenv('APP_ENV');
    }
}

function screenshotFixtureActor(): User
{
    Gate::before(fn (): bool => true);

    return User::factory()->create();
}

beforeEach(function (): void {
    $language = Language::factory()->english()->create();
    Site::factory()->default()->language($language)->withTranslations($language)->create(['name' => 'Screenshot Base']);
});

it('registers the guarded prepare and restore command', function (): void {
    expect(Artisan::all())->toHaveKey('capell:demo-kit-screenshot-fixture');

    $manifest = json_decode((string) file_get_contents(__DIR__ . '/../../../capell.json'), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($manifest)) {
        throw new RuntimeException('Demo Kit manifest must decode to an array.');
    }

    $contributions = $manifest['contributes'] ?? [];
    if (! is_array($contributions)) {
        throw new RuntimeException('Demo Kit manifest contributions must be an array.');
    }

    $consoleContribution = array_find($contributions, fn ($contribution): bool => is_array($contribution) && ($contribution['type'] ?? null) === 'console-command');

    if (! is_array($consoleContribution)) {
        throw new RuntimeException('Demo Kit manifest must declare its console command.');
    }

    $commands = $consoleContribution['commands'] ?? [];
    $commandClasses = $consoleContribution['commandClasses'] ?? [];
    if (! is_array($commands) || ! is_array($commandClasses)) {
        throw new RuntimeException('Demo Kit console command contribution must contain arrays.');
    }

    expect($commands)->toContain('capell:demo-kit-screenshot-fixture')
        ->and($commandClasses)->toContain(DemoKitScreenshotFixtureCommand::class);

    $screenshotManifest = json_decode((string) file_get_contents(__DIR__ . '/../../../docs/screenshots.json'), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($screenshotManifest)) {
        throw new RuntimeException('Demo Kit screenshot manifest must decode to an array.');
    }

    $entries = $screenshotManifest['entries'] ?? [];
    if (! is_array($entries)) {
        throw new RuntimeException('Demo Kit screenshot manifest entries must be an array.');
    }

    $fixtureEntries = array_values(array_filter(
        $entries,
        static fn (mixed $entry): bool => is_array($entry) && isset($entry['entryState']),
    ));

    expect($fixtureEntries)->toHaveCount(20);
    foreach ($fixtureEntries as $entry) {
        if (! is_array($entry)
            || data_get($entry, 'entryState.setup.command') !== 'capell:demo-kit-screenshot-fixture'
            || data_get($entry, 'entryState.restore.command') !== 'capell:demo-kit-screenshot-fixture') {
            throw new RuntimeException('Demo Kit screenshot fixture entries must use the guarded command.');
        }
    }

    $confirmationEntries = array_values(array_filter(
        $entries,
        static fn (mixed $entry): bool => is_array($entry)
            && is_string($entry['id'] ?? null)
            && str_starts_with($entry['id'], 'demo-kit-queue-confirmation'),
    ));

    expect($confirmationEntries)->toHaveCount(2);
    foreach ($confirmationEntries as $entry) {
        expect($entry['waitFor'] ?? null)
            ->toBe('[role="alertdialog"]:has-text("Confirm demo generation")');
    }

    $resetConfirmationEntries = array_values(array_filter(
        $entries,
        static fn (mixed $entry): bool => is_array($entry)
            && is_string($entry['id'] ?? null)
            && str_starts_with($entry['id'], 'demo-kit-reset-confirmation'),
    ));

    expect($resetConfirmationEntries)->toHaveCount(2);
    foreach ($resetConfirmationEntries as $entry) {
        expect($entry['waitFor'] ?? null)
            ->toBe('[role="alertdialog"]:has-text("Confirm provenance-only reset")');
    }

    $sidebarMobileEntries = array_values(array_filter(
        $entries,
        static fn (mixed $entry): bool => is_array($entry)
            && is_string($entry['id'] ?? null)
            && str_starts_with($entry['id'], 'demo-kit-admin-sidebar-menu-open-mobile'),
    ));

    expect($sidebarMobileEntries)->toHaveCount(1);
    foreach ($sidebarMobileEntries as $entry) {
        expect(data_get($entry, 'beforeWait.0.selector'))
            ->toBe('.fi-topbar-open-sidebar-btn');
    }

    withDemoKitScreenshotEnvironment(function (): void {
        capell_artisan('capell:demo-kit-screenshot-fixture', [
            '--state' => 'reuse-review',
            '--attempt-token' => 'fixture-token-1',
        ])->assertExitCode(1);
    });
});

it('requires the explicit disposable environment and a well-formed token', function (): void {
    expect(fn (): mixed => PrepareDemoKitScreenshotFixtureAction::run('reuse-review', 'fixture-token-1', screenshotFixtureActor()))
        ->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');

    withDemoKitScreenshotEnvironment(function (): void {
        expect(fn (): mixed => PrepareDemoKitScreenshotFixtureAction::run('reuse-review', 'short', screenshotFixtureActor()))
            ->toThrow(RuntimeException::class, 'explicit attempt token');
    });
});

it('allocates a unique site origin for every disposable fixture site', function (): void {
    withDemoKitScreenshotEnvironment(function (): void {
        $actor = screenshotFixtureActor();
        $fixture = PrepareDemoKitScreenshotFixtureAction::run('reuse-review', 'fixture-token-origin', $actor);
        $site = Site::query()->findOrFail($fixture->siteIds[0]);
        $baseDomain = Site::query()->where('name', 'Screenshot Base')->firstOrFail()->siteDomains()->firstOrFail();
        $fixtureDomain = $site->siteDomains()->firstOrFail();

        expect($fixtureDomain->routing_identity)->not->toBe($baseDomain->routing_identity)
            ->and($fixtureDomain->path)->not->toBe($baseDomain->path);

        RestoreDemoKitScreenshotFixtureAction::run('reuse-review', 'fixture-token-origin');
    });
});

it('prepares the queued state idempotently and restores only its owned run', function (): void {
    withDemoKitScreenshotEnvironment(function (): void {
        $actor = screenshotFixtureActor();
        $first = PrepareDemoKitScreenshotFixtureAction::run('queued', 'fixture-token-queued', $actor);
        $second = PrepareDemoKitScreenshotFixtureAction::run('queued', 'fixture-token-queued', $actor);
        $run = DemoKitGenerationRun::query()->findOrFail($first->generationRunIds[0]);

        expect($second->generationRunIds)->toBe($first->generationRunIds)
            ->and($run->status)->toBe(DemoKitGenerationRun::STATUS_QUEUED)
            ->and($run->fingerprint)->toHaveLength(64)
            ->and($run->review)->toBeArray()
            ->and(data_get($run->parameters, 'screenshot_fixture.attempt_token'))->toBe('fixture-token-queued');

        $serializedResult = json_encode($first->toArray(), JSON_THROW_ON_ERROR);
        expect($serializedResult)->not->toContain('fixture-token-queued')
            ->and($serializedResult)->toContain(hash('sha256', 'fixture-token-queued'));

        $ordinarySite = Site::factory()->create(['name' => 'Ordinary fixture neighbour']);
        $restored = RestoreDemoKitScreenshotFixtureAction::run('queued', 'fixture-token-queued');

        expect($restored->generationRunIds)->toBe($first->generationRunIds)
            ->and(DemoKitGenerationRun::query()->find($run->getKey()))->toBeNull()
            ->and($ordinarySite->fresh())->not->toBeNull()
            ->and(RestoreDemoKitScreenshotFixtureAction::run('queued', 'fixture-token-queued')->generationRunIds)->toBe([]);
    });
});
