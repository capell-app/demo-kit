<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\DemoKit\Actions\ResetDemoSitesAction;

it('deletes only named demo sites during reset', function (): void {
    $firstDemoSite = Site::factory()->create(['name' => 'Summit Works']);
    $secondDemoSite = Site::factory()->create(['name' => 'Harbour Digital']);
    $keptSite = Site::factory()->create(['name' => 'Production Site']);

    $deleted = ResetDemoSitesAction::run([
        'Summit Works',
        'Harbour Digital',
    ]);

    expect($deleted)->toBe(2)
        ->and(Site::query()->whereKey($firstDemoSite->getKey())->exists())->toBeFalse()
        ->and(Site::query()->whereKey($secondDemoSite->getKey())->exists())->toBeFalse()
        ->and(Site::query()->whereKey($keptSite->getKey())->exists())->toBeTrue();
});
