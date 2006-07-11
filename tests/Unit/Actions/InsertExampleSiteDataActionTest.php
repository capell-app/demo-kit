<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\DemoKit\Actions\InsertExampleSiteDataAction;
use Capell\DemoKit\Filament\Pages\DemoKitPage;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

it('forwards every reviewed generation option to the full demo command', function (): void {
    $command = new class extends Command
    {
        /** @var array<string, mixed> */
        public array $received = [];

        protected $signature = 'test:review-options {--url=} {--languages=*} {--sites=*} {--seed=} {--site-count=} {--page-count=} {--force}';

        public function handle(): int
        {
            $this->received = $this->options();

            return self::SUCCESS;
        }
    };
    CapellCore::getPackage(DemoKitServiceProvider::$packageName)->demoCommand = 'test:review-options';
    Artisan::registerCommand($command);

    InsertExampleSiteDataAction::run([
        'url' => 'https://example.test',
        'languages' => ['en', 'fr'],
        'sites' => ['Reviewed Site'],
        'seed' => 44,
        'site_count' => 1,
        'pages' => 3,
    ]);

    expect($command->received)->toMatchArray([
        'force' => true,
        'url' => 'https://example.test',
        'languages' => ['en', 'fr'],
        'sites' => ['Reviewed Site'],
        'seed' => 44,
        'site-count' => 1,
        'page-count' => 3,
    ]);
});

it('reports a nonzero demo command exit as a generation failure', function (): void {
    CapellCore::getPackage(DemoKitServiceProvider::$packageName)->demoCommand = 'test:failed-generation';
    Artisan::registerCommand(new class extends Command
    {
        protected $signature = 'test:failed-generation {--force}';

        public function handle(): int
        {
            return self::FAILURE;
        }
    });

    expect(function (): void {
        InsertExampleSiteDataAction::run([]);
    })
        ->toThrow(RuntimeException::class, 'Example site data installation failed.');
});

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
