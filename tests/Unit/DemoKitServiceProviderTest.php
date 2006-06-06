<?php

declare(strict_types=1);

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Enums\VendorAssetEnum;
use Capell\Core\Facades\CapellCore;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Providers\ScreenshotWorkbenchServiceProvider;

it('registers demo kit views as frontend tailwind sources', function (): void {
    expect(CapellCore::getVendorAssetsForType(VendorAssetEnum::TailwindSource)
        ->filter(fn (mixed $asset): bool => data_get($asset, 'packageName') === DemoKitServiceProvider::$packageName)
        ->pluck('value')
        ->all())->toContain('resources/views/**/*.blade.php');
});

it('waits for short SQLite writer overlap in the disposable screenshot workbench', function (): void {
    putenv('CAPELL_SCREENSHOT_WORKBENCH=true');

    try {
        (new ScreenshotWorkbenchServiceProvider(app()))->register();

        expect(config('database.connections.sqlite.busy_timeout'))->toBe(30_000);
    } finally {
        putenv('CAPELL_SCREENSHOT_WORKBENCH');
    }
});

it('enables session presentation mode and contributes the optional extensions chapter', function (): void {
    Gate::before(fn (): bool => true);
    test()->actingAs(User::factory()->create());

    $step = collect(CapellAdmin::getWelcomeTourSteps())->firstWhere('key', 'capell-demo-kit.extensions');

    expect(config('capell-welcome-tour.presentation_mode'))->toBeTrue()
        ->and($step)->not->toBeNull()
        ->and(data_get($step, 'chapter'))->toBe('extensions')
        ->and(data_get($step, 'route'))->toBe('/admin/extensions');
});

it('allows an isolated screenshot runtime to disable presentation mode', function (): void {
    $environment = Env::getRepository();
    $environment->set('CAPELL_DEMO_KIT_PRESENTATION_MODE', 'false');

    try {
        /** @var array{presentation_mode: bool} $configuration */
        $configuration = require dirname(__DIR__, 2) . '/config/capell-demo-kit.php';

        expect($configuration['presentation_mode'])->toBeFalse();
    } finally {
        $environment->clear('CAPELL_DEMO_KIT_PRESENTATION_MODE');
    }
});

it('honours disabled presentation mode when the package finishes booting', function (): void {
    config()->set('capell-demo-kit.presentation_mode', false);
    config()->set('capell-welcome-tour.presentation_mode', false);

    (new DemoKitServiceProvider(app()))->packageBooted();

    expect(config('capell-welcome-tour.presentation_mode'))->toBeFalse();
});

it('does not enable presentation mode before package configuration is merged', function (): void {
    $environment = Env::getRepository();
    $environment->set('CAPELL_DEMO_KIT_PRESENTATION_MODE', 'false');
    config()->offsetUnset('capell-demo-kit');
    config()->set('capell-welcome-tour.presentation_mode', false);

    try {
        (new DemoKitServiceProvider(app()))->registeringPackage();

        expect(config('capell-welcome-tour.presentation_mode'))->toBeFalse();
    } finally {
        $environment->clear('CAPELL_DEMO_KIT_PRESENTATION_MODE');
    }
});
