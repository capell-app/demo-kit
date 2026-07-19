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

it('enables session presentation mode without changing the seven chapter tour', function (): void {
    Gate::before(fn (): bool => true);
    test()->actingAs(User::factory()->create());

    expect(config('capell-welcome-tour.presentation_mode'))->toBeTrue()
        ->and(collect(CapellAdmin::getWelcomeTourSteps())->pluck('chapter')->unique()->values()->all())
        ->not->toContain('extensions');
});
