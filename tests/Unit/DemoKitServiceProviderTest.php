<?php

declare(strict_types=1);

use Capell\Core\Enums\VendorAssetEnum;
use Capell\Core\Facades\CapellCore;
use Capell\DemoKit\Providers\DemoKitServiceProvider;

it('registers demo kit views as frontend tailwind sources', function (): void {
    expect(CapellCore::getVendorAssetsForType(VendorAssetEnum::TailwindSource)
        ->filter(fn ($asset): bool => $asset->packageName === DemoKitServiceProvider::$packageName)
        ->pluck('value')
        ->all())->toContain('resources/views/**/*.blade.php');
});
