<?php

declare(strict_types=1);

use Capell\DemoKit\Actions\BuildDemoGenerationPlanAction;

it('builds repeatable demo plans when a seed is supplied', function (): void {
    $first = BuildDemoGenerationPlanAction::run([
        'site_count' => 2,
        'pages' => 8,
        'languages' => ['all'],
        'seed' => 1234,
    ]);

    $second = BuildDemoGenerationPlanAction::run([
        'site_count' => 2,
        'pages' => 8,
        'languages' => ['all'],
        'seed' => 1234,
    ]);

    expect($second->toArray())->toBe($first->toArray());
});

it('exposes a stable fingerprint for idempotent demo generation', function (): void {
    $first = BuildDemoGenerationPlanAction::run([
        'site_count' => 2,
        'pages' => 8,
        'languages' => ['all'],
        'seed' => 4321,
    ]);

    $second = BuildDemoGenerationPlanAction::run([
        'site_count' => 2,
        'pages' => 8,
        'languages' => ['all'],
        'seed' => 4321,
    ]);

    expect($first->fingerprint())->toBe($second->fingerprint())
        ->and($first->fingerprint())->toHaveLength(64);
});

it('builds generated plans within requested scale controls', function (): void {
    $plan = BuildDemoGenerationPlanAction::run([
        'site_count' => 4,
        'pages' => 6,
        'languages' => ['random:2'],
        'seed' => 456,
    ]);

    expect($plan->sites)->toHaveCount(4)
        ->and($plan->languageCodes)->toHaveCount(2);

    foreach ($plan->sites as $site) {
        expect($site->pageCount())->toBe(6)
            ->and($site->languageCodes)->not()->toBeEmpty();
    }
});

it('honours page counts larger than the base page name pool', function (): void {
    $plan = BuildDemoGenerationPlanAction::run([
        'site_count' => 1,
        'pages' => 50,
        'languages' => ['en'],
        'seed' => 789,
    ]);

    expect($plan->sites[0]->pageCount())->toBe(50);
});

it('reserves special pages first when page counts are small', function (): void {
    $plan = BuildDemoGenerationPlanAction::run([
        'site_count' => 1,
        'pages' => 3,
        'languages' => ['en'],
        'seed' => 246,
    ]);

    expect(demoPlanPageNames($plan->sites[0]->pages))->toBe([
        'Contact',
        'Pricing',
        'Resources',
    ]);
});

it('does not duplicate reserved footer page names in generated fallback pages', function (): void {
    $plan = BuildDemoGenerationPlanAction::run([
        'site_count' => 1,
        'pages' => 50,
        'languages' => ['en'],
        'seed' => 789,
    ]);

    $pageNames = demoPlanPageNames($plan->sites[0]->pages);

    expect(array_count_values($pageNames))
        ->toMatchArray([
            'Contact' => 1,
            'Pricing' => 1,
            'Resources' => 1,
            'Integrations' => 1,
            'Locations' => 1,
            'Partners' => 1,
            'Roadmap' => 1,
            'Governance' => 1,
            'Training' => 1,
        ]);
});

it('keeps generated demo page trees out of publishable config', function (): void {
    expect(config('capell-demo-kit.pages'))->toBeNull()
        ->and(config('capell-demo-kit.counts.pages_per_site'))->toBe([12, 30]);
});

function demoPlanPageNames(array $pages): array
{
    return collect($pages)
        ->flatMap(fn (mixed $page): array => [
            $page->name['en'],
            ...demoPlanPageNames($page->children),
        ])
        ->values()
        ->all();
}
