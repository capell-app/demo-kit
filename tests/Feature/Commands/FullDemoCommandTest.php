<?php

declare(strict_types=1);

use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Filament\Components\Forms\LanguageSelect;
use Capell\Admin\Filament\Components\Forms\SiteSelect;
use Capell\Admin\Filament\Pages\ExtensionsPage;
use Capell\Admin\Support\Breadcrumbs\ExtensionBreadcrumbDecorator;
use Capell\Admin\Support\Extensions\ExtensionPageRegistry;
use Capell\Core\Actions\DemoPackageAction;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Page;
use Capell\Core\Support\Creator\PageCreator;
use Capell\DemoKit\Actions\InsertExampleSiteDataAction;
use Capell\DemoKit\Filament\Pages\DemoKitPage;
use Capell\DemoKit\LayoutBuilder\Actions\CreateLayoutBuilderDemoSiteAction;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\DemoKit\Support\Extensions\ExampleSiteDataActionSchema;
use Capell\DemoKit\Tests\Fixtures\Commands\TrackingDemoCommand;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    DemoPackageAction::resetProcessFactory();
    DemoPackageAction::setProcessFactory(fn (array $command): object => new readonly class($command)
    {
        /** @param array<int, string> $command */
        public function __construct(private array $command) {}

        public function setTimeout(?float $timeout): self
        {
            return $this;
        }

        public function run(?callable $callback = null): int
        {
            $artisanIndex = array_search(base_path('artisan'), $this->command, true);
            assert(is_int($artisanIndex));

            $exitCode = Artisan::call($this->command[$artisanIndex + 1], $this->artisanArguments($artisanIndex + 2));

            if ($callback !== null) {
                $callback('out', Artisan::output());
            }

            return $exitCode;
        }

        public function isSuccessful(): bool
        {
            return true;
        }

        public function getExitCode(): int
        {
            return 0;
        }

        /** @return array<string, mixed> */
        private function artisanArguments(int $argumentOffset): array
        {
            return collect(array_slice($this->command, $argumentOffset))
                ->mapWithKeys(function (string $argument): array {
                    if (! str_starts_with($argument, '--')) {
                        return [];
                    }

                    if (! str_contains($argument, '=')) {
                        return [$argument => true];
                    }

                    [$name, $value] = explode('=', $argument, 2);

                    return [$name => str_contains($value, ',') ? explode(',', $value) : $value];
                })
                ->all();
        }
    });
});

afterEach(function (): void {
    DemoPackageAction::resetProcessFactory();
});

function fakeDemoKitCurrentRouteName(string $routeName): void
{
    $route = new Route(['GET'], '/testing-route', []);
    $route->name($routeName);

    request()->setRouteResolver(fn (): Route => $route);
}

it('creates full multi site and language demo data and runs package demos', function (): void {
    TrackingDemoCommand::reset();

    CapellCore::forcePackageInstalled('capell-app/content-sections');
    CapellCore::forcePackageInstalled('capell-app/layout-builder');

    CapellCore::registerPackage(name: 'vendor/example-package');
    CapellCore::forcePackageInstalled('vendor/example-package');
    CapellCore::getPackage('vendor/example-package')->demoCommand = 'test:demo';
    CapellCore::getPackage('vendor/example-package')->demoParams = ['url', 'user', 'languages', 'sites'];

    CreateLayoutBuilderDemoSiteAction::shouldRun()
        ->twice()
        ->andReturn(true);

    Artisan::registerCommand(new TrackingDemoCommand);

    app()->bind(PageCreator::class, function (): PageCreator {
        $mock = Mockery::mock(PageCreator::class . '[createHomePage,createErrorPage]');
        $mock->shouldReceive('createHomePage')->andReturnUsing(fn (): Page => new Page);
        $mock->shouldReceive('createErrorPage')->andReturnUsing(fn (): Page => new Page);

        return $mock;
    });

    app()->bind(DemoCreator::class, function (Application $app, array $params): DemoCreator {
        $mock = Mockery::mock(DemoCreator::class . '[setupRelatedSites,createPage,setupSite]', [$params['url'], $params['author']]);
        $mock->shouldReceive('setupRelatedSites')->andReturnNull();
        $mock->shouldReceive('createPage')->andReturnUsing(fn (): Page => new Page);
        $mock->shouldReceive('setupSite')->andReturnNull();

        return $mock;
    });

    test()->artisan('capell:demo-kit-full-demo', [
        '--url' => 'https://example.test',
        '--languages' => 'en,fr',
        '--sites' => 'Main Site,Sub Site',
        '--force' => true,
    ])->assertExitCode(0);

    capell_expect(TrackingDemoCommand::$executionOrder)->toBe(['test:demo']);
    capell_expect(TrackingDemoCommand::$queueConversionsByDefault)->toBeFalse();
});

it('only runs package demos selected by packages option', function (): void {
    TrackingDemoCommand::reset();

    CapellCore::forcePackageInstalled('capell-app/content-sections');
    CapellCore::forcePackageInstalled('capell-app/layout-builder');

    foreach (['vendor/selected-package', 'vendor/skipped-package'] as $packageName) {
        CapellCore::registerPackage(name: $packageName);
        CapellCore::forcePackageInstalled($packageName);
        CapellCore::getPackage($packageName)->demoCommand = $packageName === 'vendor/selected-package'
            ? 'test:selected-demo'
            : 'test:skipped-demo';
    }

    CreateLayoutBuilderDemoSiteAction::shouldRun()
        ->once()
        ->andReturn(true);

    Artisan::registerCommand(new TrackingDemoCommand('test:selected-demo {--url=} {--user=} {--languages=*} {--sites=*}'));
    Artisan::registerCommand(new TrackingDemoCommand('test:skipped-demo {--url=} {--user=} {--languages=*} {--sites=*}'));

    app()->bind(PageCreator::class, function (): PageCreator {
        $mock = Mockery::mock(PageCreator::class . '[createHomePage,createErrorPage]');
        $mock->shouldReceive('createHomePage')->andReturnUsing(fn (): Page => new Page);
        $mock->shouldReceive('createErrorPage')->andReturnUsing(fn (): Page => new Page);

        return $mock;
    });

    app()->bind(DemoCreator::class, function (Application $app, array $params): DemoCreator {
        $mock = Mockery::mock(DemoCreator::class . '[setupRelatedSites,createPage,setupSite]', [$params['url'], $params['author']]);
        $mock->shouldReceive('setupRelatedSites')->andReturnNull();
        $mock->shouldReceive('createPage')->andReturnUsing(fn (): Page => new Page);
        $mock->shouldReceive('setupSite')->andReturnNull();

        return $mock;
    });

    test()->artisan('capell:demo-kit-full-demo', [
        '--url' => 'https://example.test',
        '--languages' => 'en',
        '--sites' => 'Main Site',
        '--page-count' => 1,
        '--packages' => 'vendor/selected-package',
        '--force' => true,
    ])->assertExitCode(0);

    capell_expect(TrackingDemoCommand::$executionOrder)->toBe(['test:selected-demo']);
});

it('requires force when running non interactively', function (): void {
    test()->artisan('capell:demo-kit-full-demo', [
        '--url' => 'https://example.test',
        '--no-interaction' => true,
    ])->assertExitCode(1);
});

it('registers its package owned extension page', function (): void {
    fakeDemoKitCurrentRouteName(DemoKitPage::getRouteName());

    capell_expect(CapellAdmin::getAdminSurfaceRegistry()->pages())->toContain(DemoKitPage::class);
    capell_expect(resolve(ExtensionPageRegistry::class)->get(DemoKitServiceProvider::$packageName))->toBe(DemoKitPage::class);
    capell_expect(DemoKitPage::getNavigationGroup())->toBe(__('capell-admin::navigation.group_system'));

    if (! class_exists(ExtensionBreadcrumbDecorator::class)) {
        return;
    }

    capell_expect(resolve(ExtensionBreadcrumbDecorator::class)->decorate([]))->toBe([
        ExtensionsPage::getUrl() => __('capell-admin::navigation.extensions'),
        resolve(DemoKitPage::class)->getTitle(),
    ]);
});

it('builds the insert example site data schema', function (): void {
    $schema = resolve(ExampleSiteDataActionSchema::class)->schema();

    capell_expect($schema)
        ->toHaveCount(3)
        ->and($schema[0])->toBeInstanceOf(TextInput::class)
        ->and($schema[1])->toBeInstanceOf(LanguageSelect::class)
        ->and($schema[2])->toBeInstanceOf(SiteSelect::class);
});

it('inserts example site data through the registered demo command', function (): void {
    TrackingDemoCommand::reset();

    CapellCore::getPackage(DemoKitServiceProvider::$packageName)->demoCommand = 'test:insert-example-site-data';

    Artisan::registerCommand(new TrackingDemoCommand(
        'test:insert-example-site-data {--url=} {--user=} {--languages=*} {--sites=*} {--force}',
    ));

    InsertExampleSiteDataAction::run([
        'url' => 'https://example.test',
        'languages' => ['en', 'fr'],
        'sites' => ['Main Site'],
    ]);

    capell_expect(TrackingDemoCommand::$executionOrder)->toBe(['test:insert-example-site-data']);
});
