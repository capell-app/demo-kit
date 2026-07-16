<?php

declare(strict_types=1);

use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\Theme;
use Capell\DemoKit\Actions\CreateDemoSiteAction;
use Capell\DemoKit\Actions\HasDemoSiteProvenanceAction;
use Capell\DemoKit\Actions\MarkDemoSiteProvenanceAction;
use Capell\DemoKit\Actions\ResetDemoSitesAction;

it('deletes only named sites carrying demo kit provenance during reset', function (): void {
    $demoSite = MarkDemoSiteProvenanceAction::run(
        Site::factory()->create(['name' => 'Harbour Digital']),
    );
    $unmarkedNamedSite = Site::factory()->create(['name' => 'Summit Works']);
    $unrequestedDemoSite = MarkDemoSiteProvenanceAction::run(
        Site::factory()->create(['name' => 'Demo Reference']),
    );

    $deleted = ResetDemoSitesAction::run([
        'Summit Works',
        'Harbour Digital',
    ]);

    expect($deleted)->toBe(1)
        ->and(Site::query()->whereKey($demoSite->getKey())->exists())->toBeFalse()
        ->and(Site::query()->whereKey($unmarkedNamedSite->getKey())->exists())->toBeTrue()
        ->and(Site::query()->whereKey($unrequestedDemoSite->getKey())->exists())->toBeTrue();
});

it('refuses to reuse an unmarked site as a demo site', function (): void {
    $site = Site::factory()->create(['name' => 'Customer Site']);

    expect(fn (): Site => CreateDemoSiteAction::run(
        name: $site->name,
        url: 'https://example.test',
        language: $site->language,
        languages: collect([$site->language]),
    ))->toThrow(
        RuntimeException::class,
        'Refusing to replace site [Customer Site] because it was not provisioned by Demo Kit.',
    );
});

it('explicitly adopts an existing unmarked site without replacing it', function (): void {
    $site = Site::factory()->create(['name' => 'Installed Site']);

    $adoptedSite = CreateDemoSiteAction::run(
        name: $site->name,
        url: 'https://example.test',
        language: $site->language,
        languages: collect([$site->language]),
        adoptExistingSite: true,
    );

    expect($adoptedSite->is($site))->toBeTrue()
        ->and(HasDemoSiteProvenanceAction::run($adoptedSite))->toBeTrue()
        ->and(Site::query()->where('name', 'Installed Site')->count())->toBe(1);
});

it('marks newly created demo sites with durable provenance', function (): void {
    $language = Language::factory()->english()->create();
    Blueprint::factory()->site()->default()->create();
    Theme::factory()->default()->create();

    $site = CreateDemoSiteAction::run(
        name: 'Verified Demo Site',
        url: 'https://demo.example.test',
        language: $language,
        languages: collect([$language]),
    );

    expect(HasDemoSiteProvenanceAction::run($site))->toBeTrue()
        ->and(data_get($site->meta, 'demo_kit.provisioned_at'))->toBeString()->not->toBeEmpty();
});
