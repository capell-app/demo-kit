<?php

declare(strict_types=1);

use Capell\DemoKit\Actions\InsertExampleSiteDataAction;
use Capell\DemoKit\Filament\Pages\DemoKitPage;

it('blocks the admin demo surface outside local and testing environments', function (): void {
    $originalEnvironment = app()->make('env');
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        expect(DemoKitPage::canAccess())->toBeFalse()
            ->and(function (): void {
                InsertExampleSiteDataAction::run([]);
            })
            ->toThrow(
                RuntimeException::class,
                'Example site data can only be installed from the admin panel in local or testing environments.',
            );
    } finally {
        app()->detectEnvironment(static fn (): string => is_string($originalEnvironment) ? $originalEnvironment : 'testing');
    }
});
