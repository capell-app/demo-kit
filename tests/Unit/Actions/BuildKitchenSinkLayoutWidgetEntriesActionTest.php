<?php

declare(strict_types=1);

use Capell\DemoKit\Actions\BuildKitchenSinkLayoutWidgetEntriesAction;

it('builds deterministic kitchen sink widget entries with lazy thresholds', function (): void {
    $entries = BuildKitchenSinkLayoutWidgetEntriesAction::run(30, 5);

    expect($entries)->toHaveCount(30)
        ->and($entries[0])->toMatchArray([
            'widget_key' => 'kitchen-sink-001-kitchen-sink-hero-top',
            'source_key' => 'kitchen-sink-hero-top',
            'occurrence' => 1,
            'stress_index' => 1,
            'variant' => 'baseline',
            'lazy' => false,
        ])
        ->and($entries[5]['lazy'])->toBeTrue()
        ->and(collect($entries)->pluck('widget_key')->unique())->toHaveCount(30);
});
