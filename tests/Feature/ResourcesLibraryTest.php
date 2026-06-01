<?php

declare(strict_types=1);

use Capell\DemoKit\Livewire\ResourcesLibrary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

it('filters and paginates demo resources through the public library component', function (): void {
    $items = [
        ['title' => 'Launch checklist', 'label' => 'Guides', 'copy' => 'Plan the site launch with owners and deadlines.', 'url' => '/guides/launch'],
        ['title' => 'Homepage wireframe', 'label' => 'Templates', 'copy' => 'Start from a reusable page structure.', 'url' => '/templates/homepage'],
        ['title' => 'Editorial calendar', 'label' => 'Guides', 'copy' => 'Coordinate publishing work across languages.', 'url' => '/guides/calendar'],
        ['title' => 'Brand worksheet', 'label' => 'Worksheets', 'copy' => 'Capture voice, tone, and visual decisions.', 'url' => '/worksheets/brand'],
    ];

    Livewire::test(ResourcesLibrary::class, [
        'items' => $items,
        'filters' => ['Guides', 'Templates', 'Worksheets'],
        'cta' => ['label' => 'Browse all', 'href' => '/resources'],
    ])
        ->assertSet('filter', 'All resources')
        ->assertViewHas('activeFilter', 'All resources')
        ->assertViewHas('resources', fn (mixed $resources): bool => $resources instanceof LengthAwarePaginator
                && $resources->total() === 4
                && $resources->count() === 3)
        ->call('selectFilter', 'Guides')
        ->assertSet('filter', 'Guides')
        ->assertViewHas('activeFilter', 'Guides')
        ->assertViewHas('resources', fn (mixed $resources): bool => $resources instanceof LengthAwarePaginator
                && $resources->total() === 2
                && $resources->pluck('title')->all() === ['Launch checklist', 'Editorial calendar'])
        ->call('selectFilter', 'Missing topic')
        ->assertSet('filter', 'All resources');
});
