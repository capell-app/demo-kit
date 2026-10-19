<?php

declare(strict_types=1);

use Capell\DemoKit\Support\DemoKitScreenshotFixtureGuard;

it('refuses a real local application even when its physical path and fixture flags match', function (): void {
    $previousFixture = getenv('CAPELL_SCREENSHOT_FIXTURE');
    $previousPath = getenv('CAPELL_SCREENSHOT_APP_PATH');
    $basePath = app()->basePath();
    $root = sys_get_temp_dir() . '/capell-real-local-app-' . bin2hex(random_bytes(8));
    mkdir($root);
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . $root);
    app()->setBasePath($root);

    try {
        expect(DemoKitScreenshotFixtureGuard::assertEnvironment(...))
            ->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
    } finally {
        app()->setBasePath($basePath);
        putenv($previousFixture === false ? 'CAPELL_SCREENSHOT_FIXTURE' : 'CAPELL_SCREENSHOT_FIXTURE=' . $previousFixture);
        putenv($previousPath === false ? 'CAPELL_SCREENSHOT_APP_PATH' : 'CAPELL_SCREENSHOT_APP_PATH=' . $previousPath);
        rmdir($root);
    }
});

it('rejects an unsupported fixture state and a weak attempt token', function (): void {
    expect(fn (): string => DemoKitScreenshotFixtureGuard::state('unknown'))->toThrow(RuntimeException::class)
        ->and(fn (): string => DemoKitScreenshotFixtureGuard::attemptToken('short'))->toThrow(RuntimeException::class)
        ->and(DemoKitScreenshotFixtureGuard::state(' queued '))->toBe('queued');
});
