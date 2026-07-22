<?php

declare(strict_types=1);

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Enums\VendorAssetEnum;
use Capell\Core\Facades\CapellCore;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;

it('registers demo kit views as frontend tailwind sources', function (): void {
    expect(CapellCore::getVendorAssetsForType(VendorAssetEnum::TailwindSource)
        ->filter(fn (mixed $asset): bool => data_get($asset, 'packageName') === DemoKitServiceProvider::$packageName)
        ->pluck('value')
        ->all())->toContain('resources/views/**/*.blade.php');
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
