<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Page;
use Capell\Core\Support\Creator\PageCreator;
use Capell\DemoKit\Actions\BuildDemoGenerationPlanAction;
use Capell\DemoKit\Data\DemoGenerationPlanData;
use Capell\DemoKit\Data\DemoPagePlanData;
use Capell\DemoKit\Data\DemoProfileData;
use Capell\DemoKit\Data\DemoSiteGenerationPlanData;
use Capell\DemoKit\LayoutBuilder\Actions\CreateLayoutBuilderDemoSiteAction;
use Capell\DemoKit\LayoutBuilder\Data\DemoSitePlanData;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\DemoKit\Support\Creator\DemoResourceResolver;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('keeps progress messages short enough to preserve the bar width', function (): void {
    CapellCore::forcePackageInstalled('capell-app/content-sections', false);
    CapellCore::forcePackageInstalled('capell-app/layout-builder', false);
    $user = User::factory()->create(['email' => 'progress@example.test']);
    $longName = 'Dogs Spaniel Springer Spaniel English Springer Spaniel Show English Springer Spaniel';
    $planner = Mockery::mock(new BuildDemoGenerationPlanAction);
    app()->instance('LaravelActions:AsFake:' . BuildDemoGenerationPlanAction::class, $planner);
    $planner->shouldReceive('handle')->once()->andReturn(new DemoGenerationPlanData(
        seed: 7,
        languageCodes: ['en'],
        sites: [new DemoSiteGenerationPlanData('Progress Site', ['en'], [new DemoPagePlanData(['en' => $longName], mediaCount: 0)])],
        profile: DemoProfileData::default(),
    ));
    $pages = Mockery::mock(PageCreator::class);
    $pages->shouldReceive('createHomePage')->once()->andReturn(new Page);
    $pages->shouldReceive('createErrorPage')->once()->andReturn(new Page);
    app()->instance(PageCreator::class, $pages);
    app()->bind(DemoCreator::class, function (Application $app, array $params): DemoCreator {
        $creator = Mockery::mock(DemoCreator::class . '[setupRelatedSites,createPage,setupSite]', [$params['url'], $params['author']]);
        $creator->shouldReceive('setupRelatedSites')->once()->andReturnNull();
        $creator->shouldReceive('createPage')->once()->andReturn(new Page);
        $creator->shouldReceive('setupSite')->twice()->andReturnNull();

        return $creator;
    });
    $exit = Artisan::call('capell:admin-demo', ['--url' => 'https://example.test', '--user' => $user->email, '--languages' => 'en', '--sites' => 'Progress Site']);
    $output = Artisan::output();
    preg_match_all('/— ([^\r\n]+)/u', $output, $matches);
    expect($exit)->toBe(0)->and($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $message) {
        expect(mb_strlen(trim($message)))->toBeLessThanOrEqual(32);
    }

    expect(collect($matches[1])->filter(static fn (string $message): bool => str_ends_with(trim($message), '...')))->not->toBeEmpty();
});

it('runs demo command successfully', function (): void {
    CapellCore::forcePackageInstalled('capell-app/content-sections');
    CapellCore::forcePackageInstalled('capell-app/layout-builder');

    $user = User::factory()->create(['email' => 'admin@example.com']);
    $demoDirectory = storage_path('framework/testing/demo-kit-demo');

    File::ensureDirectoryExists($demoDirectory . '/img');
    File::ensureDirectoryExists($demoDirectory . '/video');
    $image = imagecreatetruecolor(4, 4);
    imagejpeg($image, $demoDirectory . '/img/demo.jpg');
    imagedestroy($image);

    $zipPath = tempnam(sys_get_temp_dir(), 'capell-demo-kit-') . '.zip';
    $archive = new ZipArchive;
    $archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('demo/img/.gitkeep', '');
    $archive->addFromString('demo/video/.gitkeep', '');
    $archive->close();

    config()->set('capell-demo-kit.archive.checksum', hash_file('sha256', $zipPath));
    config()->set('capell-demo.archive.checksum', hash_file('sha256', $zipPath));

    Http::fake([
        '*' => Http::response(File::get($zipPath), 200),
    ]);

    app()->instance(DemoResourceResolver::class, new readonly class($demoDirectory)
    {
        public function __construct(private string $demoDirectory) {}

        public function resolve(?string $folder): string
        {
            $folder = in_array($folder, [null, '', '0'], true) ? null : ltrim($folder, '/');

            return $this->demoDirectory . ($folder === null ? '' : '/' . $folder);
        }

        public function ensureStorageDemoResources(): string
        {
            return $this->demoDirectory;
        }

        public function assertSafeDemoZipEntries(ZipArchive $zip): void {}
    });

    app()->bind(PageCreator::class, function (): PageCreator {
        $mock = Mockery::mock(PageCreator::class . '[createHomePage,createErrorPage]');
        $mock->shouldReceive('createHomePage')->andReturnUsing(fn (): Page => new Page);
        $mock->shouldReceive('createErrorPage')->andReturnUsing(fn (): Page => new Page);

        return $mock;
    });

    app()->bind(DemoCreator::class, function (Application $app, array $params) use ($user): DemoCreator {
        expect($params['author']?->getKey())->toBe($user->getKey());

        $mock = Mockery::mock(DemoCreator::class . '[setupRelatedSites,createPage,setupSite,setupMainNavigation,setupFooterNavigation,subFooterNavigation]', [$params['url'], $params['author']]);
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('setupRelatedSites')->andReturnNull();
        $mock->shouldReceive('createPage')->andReturnUsing(fn (): Page => new Page);
        $mock->shouldReceive('setupSite')->times(4)->andReturnNull();
        $mock->shouldReceive('setupMainNavigation')->andReturnNull();
        $mock->shouldReceive('setupFooterNavigation')->andReturnNull();
        $mock->shouldReceive('subFooterNavigation')->andReturnNull();

        return $mock;
    });

    CreateLayoutBuilderDemoSiteAction::shouldRun()
        ->twice()
        ->with(Mockery::on(fn (DemoSitePlanData $plan): bool => $plan->contentTree['children'] !== []
            && $plan->user?->is($user)))
        ->andReturn(true);

    capell_artisan('capell:admin-demo', [
        '--url' => 'https://example.test',
        '--user' => $user->email,
        '--languages' => 'en,fr',
        '--sites' => 'Main Site,Sub Site',
    ])->assertExitCode(0);

    $output = str_replace("\r", "\n", Artisan::output());

    expect($output)
        ->not->toContain('Starting...')
        ->not->toContain(PHP_EOL . 'Home page' . PHP_EOL)
        ->not->toContain(PHP_EOL . 'Error page' . PHP_EOL)
        ->not->toContain(PHP_EOL . 'Setting up site' . PHP_EOL)
        ->not->toContain(PHP_EOL . 'Setting up pages' . PHP_EOL);

    File::delete($zipPath);
});

it('skips layout builder demo when the layout demo packages are not installed', function (): void {
    CapellCore::forcePackageInstalled('capell-app/content-sections', false);
    CapellCore::forcePackageInstalled('capell-app/layout-builder', false);

    $user = User::factory()->create(['email' => 'admin@example.com']);

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

    CreateLayoutBuilderDemoSiteAction::shouldRun()
        ->never();

    capell_artisan('capell:admin-demo', [
        '--url' => 'https://example.test',
        '--user' => $user->email,
        '--languages' => 'en',
        '--sites' => 'Main Site',
    ])->assertExitCode(0);
});

it('skips default demo users when requested', function (): void {
    CapellCore::forcePackageInstalled('capell-app/content-sections', false);
    CapellCore::forcePackageInstalled('capell-app/layout-builder', false);

    $user = User::factory()->create(['email' => 'admin@example.com']);

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

    capell_artisan('capell:admin-demo', [
        '--url' => 'https://example.test',
        '--user' => $user->email,
        '--languages' => 'en',
        '--sites' => 'Main Site',
        '--skip-demo-users' => true,
    ])->assertExitCode(0);

    expect(User::query()->where('email', 'demo@example.com')->exists())->toBeFalse();
});

it('rejects invalid numeric demo scale options', function (): void {
    expect(fn () => capell_artisan('capell:admin-demo', [
        '--url' => 'https://example.test',
        '--languages' => 'en',
        '--sites' => 'Main Site',
        '--page-count' => 'abc',
    ]))->toThrow(InvalidArgumentException::class, 'The --page-count option must be a positive integer.');
});
