<?php

declare(strict_types=1);

use Capell\DemoKit\Livewire\ResourcesLibrary;
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
        ->assertSee('Launch checklist')
        ->assertSee('Homepage wireframe')
        ->assertSee('Editorial calendar')
        ->assertDontSee('Brand worksheet')
        ->call('selectFilter', 'Guides')
        ->assertSet('filter', 'Guides')
        ->assertSee('Launch checklist')
        ->assertSee('Editorial calendar')
        ->assertDontSee('Homepage wireframe')
        ->assertDontSee('Brand worksheet')
        ->call('selectFilter', 'Missing topic')
        ->assertSet('filter', 'All resources');
});
