<?php

declare(strict_types=1);

use Capell\Core\Models\Page;

beforeEach(function (): void {
    config()->set('capell-demo-kit.kitchen_sink.target_widget_count', 12);
    config()->set('capell-demo-kit.kitchen_sink.eager_widget_limit', 11);
    config()->set('capell-demo-kit.kitchen_sink.context_page_count', 4);
    config()->set('capell-demo-kit.kitchen_sink.context_asset_limit', 4);
});

it('installs the Kitchen Sink Demo Page', function (): void {
    capell_artisan('capell:demo-kit-kitchen-sink')->assertExitCode(0);

    expect(Page::query()->where('name', 'Kitchen Sink Demo Page')->exists())->toBeTrue();
});

it('refuses to run in the production environment without an override', function (): void {
    $originalEnvironment = app()->make('env');
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        capell_artisan('capell:demo-kit-kitchen-sink')->assertExitCode(1);

        expect(Page::query()->where('name', 'Kitchen Sink Demo Page')->exists())->toBeFalse();
    } finally {
        app()->detectEnvironment(static fn (): string => is_string($originalEnvironment) ? $originalEnvironment : 'testing');
    }
});

it('runs in production when --allow-production is supplied', function (): void {
    $originalEnvironment = app()->make('env');
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        capell_artisan('capell:demo-kit-kitchen-sink', ['--allow-production' => true])->assertExitCode(0);

        expect(Page::query()->where('name', 'Kitchen Sink Demo Page')->exists())->toBeTrue();
    } finally {
        app()->detectEnvironment(static fn (): string => is_string($originalEnvironment) ? $originalEnvironment : 'testing');
    }
});
