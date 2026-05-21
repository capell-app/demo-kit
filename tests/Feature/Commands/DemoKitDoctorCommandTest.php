<?php

declare(strict_types=1);

it('reports demo health failures as json', function (): void {
    test()->artisan('capell:demo-kit-doctor', [
        '--json' => true,
    ])
        ->expectsOutputToContain('"status": "failed"')
        ->assertExitCode(1);
});

it('reports demo health failures for console readers', function (): void {
    test()->artisan('capell:demo-kit-doctor')
        ->expectsOutputToContain('Demo Kit Health Check')
        ->expectsOutputToContain('One or more checks failed. See suggestions above.')
        ->assertExitCode(1);
});
