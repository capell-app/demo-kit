<?php

declare(strict_types=1);

use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Filament\Components\Forms\LanguageSelect;
use Capell\Admin\Filament\Components\Forms\SiteSelect;
use Capell\Admin\Filament\Pages\ExtensionsPage;
use Capell\Admin\Support\Breadcrumbs\ExtensionBreadcrumbDecorator;
use Capell\Admin\Support\Extensions\ExtensionPageRegistry;
use Capell\Core\Actions\DemoPackageAction;
use Capell\Core\Enums\PackageTypeEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\PageCreator;
use Capell\DemoKit\Actions\HasDemoSiteProvenanceAction;
use Capell\DemoKit\Actions\InsertExampleSiteDataAction;
use Capell\DemoKit\Filament\Pages\DemoKitPage;
use Capell\DemoKit\LayoutBuilder\Actions\CreateLayoutBuilderDemoSiteAction;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\DemoKit\Support\Extensions\ExampleSiteDataActionSchema;
use Capell\DemoKit\Tests\Fixtures\Commands\TrackingDemoCommand;
use Capell\Tests\Fixtures\Models\User;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    resolve(Kernel::class)->registerCommand(
        new class extends Command
        {
            protected $signature = 'capell:navigation-demo {--sites=} {--languages=}';

            public function handle(): int
            {
                return self::SUCCESS;
            }
        },
    );
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

    $installedSite = Site::factory()->create(['name' => 'Main Site']);

    CapellCore::forcePackageInstalled('capell-app/content-sections');
    CapellCore::forcePackageInstalled('capell-app/layout-builder');

    CapellCore::registerPackage(name: 'vendor/example-package');
    CapellCore::forcePackageInstalled('vendor/example-package');
    CapellCore::getPackage('vendor/example-package')->demoCommand = 'test:demo';
    CapellCore::getPackage('vendor/example-package')->demoParams = ['url', 'user', 'languages', 'sites', 'seed'];

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

    capell_artisan('capell:demo-kit-full-demo', [
        '--url' => 'https://example.test',
        '--languages' => 'en,fr',
        '--sites' => 'Main Site,Sub Site',
        '--seed' => 1234,
        '--adopt-existing-site' => true,
        '--skip-demo-users' => true,
        '--force' => true,
    ])->assertExitCode(0);

    capell_expect(TrackingDemoCommand::$executionOrder)->toBe(['test:demo']);
    capell_expect(TrackingDemoCommand::$queueConversionsByDefault)->toBeFalse();
    capell_expect(TrackingDemoCommand::$receivedSeedByCommand)->toBe(['test:demo' => '1234']);
    capell_expect(User::query()->where('email', 'demo@example.com')->exists())->toBeFalse();
    capell_expect(Site::query()->where('name', 'Main Site')->sole()->is($installedSite))->toBeTrue();
    capell_expect(HasDemoSiteProvenanceAction::run($installedSite->refresh()))->toBeTrue();
    capell_expect(Page::query()->where('name', 'Kitchen Sink Demo Page')->exists())->toBeTrue();
    capell_expect(Layout::query()->where('key', 'kitchen-sink-demo')->exists())->toBeTrue();
});

it('forwards the chosen author username to package demos', function (): void {
    TrackingDemoCommand::reset();

    $author = User::factory()->create(['email' => 'author@example.com']);

    CapellCore::forcePackageInstalled('capell-app/content-sections');
    CapellCore::forcePackageInstalled('capell-app/layout-builder');

    CapellCore::registerPackage(name: 'vendor/example-package');
    CapellCore::forcePackageInstalled('vendor/example-package');
    CapellCore::getPackage('vendor/example-package')->demoCommand = 'test:demo';
    CapellCore::getPackage('vendor/example-package')->demoParams = ['url', 'user', 'languages', 'sites'];

    CreateLayoutBuilderDemoSiteAction::shouldRun()
        ->once()
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

    capell_artisan('capell:demo-kit-full-demo', [
        '--url' => 'https://example.test',
        '--user' => $author->email,
        '--languages' => 'en',
        '--sites' => 'Main Site',
        '--force' => true,
    ])->assertExitCode(0);

    capell_expect(TrackingDemoCommand::$receivedUserByCommand)->toBe(['test:demo' => 'author@example.com']);
});

it('refuses to run in the production environment without an override', function (): void {
    $originalEnvironment = app()->make('env');
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        capell_artisan('capell:demo-kit-full-demo', [
            '--url' => 'https://example.test',
            '--languages' => 'en',
            '--sites' => 'Main Site',
            '--force' => true,
        ])->assertExitCode(1);

        capell_expect(User::query()->where('email', 'demo@example.com')->exists())->toBeFalse();
    } finally {
        app()->detectEnvironment(static fn (): string => is_string($originalEnvironment) ? $originalEnvironment : 'testing');
    }
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

    capell_artisan('capell:demo-kit-full-demo', [
        '--url' => 'https://example.test',
        '--languages' => 'en',
        '--sites' => 'Main Site',
        '--page-count' => 1,
        '--packages' => 'vendor/selected-package',
        '--force' => true,
    ])->assertExitCode(0);

    capell_expect(TrackingDemoCommand::$executionOrder)->toBe(['test:selected-demo']);
});

it('uses a compact quick profile when full demo counts are omitted', function (): void {
    TrackingDemoCommand::reset();

    CapellCore::forcePackageInstalled('capell-app/content-sections');
    CapellCore::forcePackageInstalled('capell-app/layout-builder');

    CapellCore::registerPackage(name: 'vendor/quick-package');
    CapellCore::forcePackageInstalled('vendor/quick-package');
    CapellCore::getPackage('vendor/quick-package')->demoCommand = 'test:quick-demo';
    CapellCore::getPackage('vendor/quick-package')->demoParams = ['url', 'languages', 'sites'];

    CreateLayoutBuilderDemoSiteAction::shouldRun()
        ->once()
        ->andReturn(true);

    Artisan::registerCommand(new TrackingDemoCommand('test:quick-demo {--url=} {--languages=*} {--sites=*}'));

    app()->bind(PageCreator::class, function (): PageCreator {
        $mock = Mockery::mock(PageCreator::class . '[createHomePage,createErrorPage]');
        $mock->shouldReceive('createHomePage')->andReturnUsing(fn (): Page => new Page);
        $mock->shouldReceive('createErrorPage')->andReturnUsing(fn (): Page => new Page);

        return $mock;
    });

    app()->bind(DemoCreator::class, function (Application $app, array $params): DemoCreator {
        $mock = Mockery::mock(DemoCreator::class . '[setupRelatedSites,createPage,setupSite]', [$params['url'], $params['author']]);
        $mock->shouldReceive('setupRelatedSites')->andReturnNull();
        $mock->shouldReceive('createPage')->times(3)->andReturnUsing(fn (): Page => new Page);
        $mock->shouldReceive('setupSite')->twice()->andReturnNull();

        return $mock;
    });

    capell_artisan('capell:demo-kit-full-demo', [
        '--url' => 'https://example.test',
        '--packages' => 'vendor/quick-package',
        '--quick' => true,
        '--force' => true,
    ])->assertExitCode(0);

    capell_expect(TrackingDemoCommand::$executionOrder)->toBe(['test:quick-demo'])
        ->and((array) (TrackingDemoCommand::$receivedLanguagesByCommand['test:quick-demo'] ?? []))->toBe(['en'])
        ->and((array) (TrackingDemoCommand::$receivedSitesByCommand['test:quick-demo'] ?? []))->toHaveCount(1);
});

it('runs non theme package demos and only the selected theme demo when theme option is provided', function (): void {
    TrackingDemoCommand::reset();

    CapellCore::forcePackageInstalled('capell-app/content-sections');
    CapellCore::forcePackageInstalled('capell-app/layout-builder');

    CapellCore::registerPackage(name: 'vendor/example-package');
    CapellCore::forcePackageInstalled('vendor/example-package');
    CapellCore::getPackage('vendor/example-package')->demoCommand = 'test:package-demo';

    CapellCore::registerPackage(name: 'capell-app/theme-liquid-glass', type: PackageTypeEnum::Theme);
    CapellCore::forcePackageInstalled('capell-app/theme-liquid-glass');
    CapellCore::getPackage('capell-app/theme-liquid-glass')->demoCommand = 'test:liquid-glass-demo';
    CapellCore::getPackage('capell-app/theme-liquid-glass')->themeKey = 'liquid-glass';

    CapellCore::registerPackage(name: 'capell-app/theme-platform', type: PackageTypeEnum::Theme);
    CapellCore::forcePackageInstalled('capell-app/theme-platform');
    CapellCore::getPackage('capell-app/theme-platform')->demoCommand = 'test:platform-demo';
    CapellCore::getPackage('capell-app/theme-platform')->themeKey = 'platform';

    CreateLayoutBuilderDemoSiteAction::shouldRun()
        ->once()
        ->andReturn(true);

    Artisan::registerCommand(new TrackingDemoCommand('test:package-demo {--url=} {--user=} {--languages=*} {--sites=*}'));
    Artisan::registerCommand(new TrackingDemoCommand('test:liquid-glass-demo {--url=} {--user=} {--languages=*} {--sites=*}'));
    Artisan::registerCommand(new TrackingDemoCommand('test:platform-demo {--url=} {--user=} {--languages=*} {--sites=*}'));

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

    capell_artisan('capell:demo-kit-full-demo', [
        '--url' => 'https://example.test',
        '--languages' => 'en',
        '--sites' => 'Main Site',
        '--page-count' => 1,
        '--theme' => 'liquid-glass',
        '--force' => true,
    ])->assertExitCode(0);

    capell_expect(TrackingDemoCommand::$executionOrder)
        ->toHaveCount(2)
        ->toContain('test:package-demo')
        ->toContain('test:liquid-glass-demo')
        ->not->toContain('test:platform-demo');
});

it('requires force when running non interactively', function (): void {
    capell_artisan('capell:demo-kit-full-demo', [
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
        ->toHaveCount(4)
        ->and($schema[0])->toBeInstanceOf(Radio::class)
        ->and($schema[1])->toBeInstanceOf(TextInput::class)
        ->and($schema[2])->toBeInstanceOf(LanguageSelect::class)
        ->and($schema[3])->toBeInstanceOf(SiteSelect::class);
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
