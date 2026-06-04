<?php

declare(strict_types=1);

use Capell\Core\Actions\DemoPackageAction;
use Capell\Core\Facades\CapellCore;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Tests\Fixtures\Commands\TrackingDemoCommand;
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

it('runs core demo command successfully', function (): void {
    TrackingDemoCommand::reset();

    CapellCore::registerPackage(name: 'vendor/example-package');

    $package = CapellCore::getPackage('vendor/example-package');
    $package->demoCommand = 'test:demo';
    $package->demoParams = ['url', 'user', 'languages', 'sites', 'seed'];

    Artisan::registerCommand(new TrackingDemoCommand);

    test()->artisan('capell:demo', [
        '--url' => 'https://example.test',
        '--user' => 'author@example.com',
        '--languages' => 'en,fr',
        '--sites' => 'Main Site,Sub Site',
        '--seed' => 9876,
        '--packages' => 'vendor/example-package',
    ])
        ->expectsQuestion('Are you sure you want to install example site content?', true)
        ->assertExitCode(0);

    expect(TrackingDemoCommand::$executionOrder)->toBe(['test:demo'])
        ->and(TrackingDemoCommand::$receivedUserByCommand)->toBe(['test:demo' => 'author@example.com'])
        ->and(TrackingDemoCommand::$receivedSeedByCommand)->toBe(['test:demo' => '9876']);
});

it('only forwards seed to package demos that declare the seed parameter', function (): void {
    TrackingDemoCommand::reset();

    CapellCore::registerPackage(name: 'vendor/seeded-package');
    CapellCore::registerPackage(name: 'vendor/unseeded-package');

    CapellCore::getPackage('vendor/seeded-package')->demoCommand = 'seeded:demo';
    CapellCore::getPackage('vendor/seeded-package')->demoParams = ['url', 'seed'];
    CapellCore::getPackage('vendor/seeded-package')->sort = 10;

    CapellCore::getPackage('vendor/unseeded-package')->demoCommand = 'unseeded:demo';
    CapellCore::getPackage('vendor/unseeded-package')->demoParams = ['url'];
    CapellCore::getPackage('vendor/unseeded-package')->sort = 20;

    Artisan::registerCommand(new TrackingDemoCommand('seeded:demo {--url=} {--seed=}'));
    Artisan::registerCommand(new TrackingDemoCommand('unseeded:demo {--url=}'));

    test()->artisan('capell:demo', [
        '--url' => 'https://example.test',
        '--packages' => 'vendor/seeded-package,vendor/unseeded-package',
        '--languages' => 'en',
        '--seed' => 4321,
        '--sites' => 'Main Site',
        '--force' => true,
    ])->assertExitCode(0);

    expect(TrackingDemoCommand::$executionOrder)->toBe(['seeded:demo', 'unseeded:demo'])
        ->and(TrackingDemoCommand::$receivedSeedByCommand)->toBe(['seeded:demo' => '4321']);
});

it('runs demo commands in package workflow order', function (): void {
    TrackingDemoCommand::reset();

    CapellCore::registerPackage(name: 'capell-app/worktree');
    CapellCore::registerPackage(name: 'capell-app/blog');
    CapellCore::registerPackage(name: 'capell-app/form-builder');
    CapellCore::registerPackage(name: DemoKitServiceProvider::$packageName);

    CapellCore::getPackage('capell-app/worktree')->demoCommand = 'worktree:demo';
    CapellCore::getPackage('capell-app/worktree')->sort = 1;
    CapellCore::getPackage('capell-app/blog')->demoCommand = 'blog:demo';
    CapellCore::getPackage('capell-app/blog')->sort = 30;
    CapellCore::getPackage('capell-app/form-builder')->demoCommand = 'form-builder:demo';
    CapellCore::getPackage('capell-app/form-builder')->sort = 10;

    Artisan::registerCommand(new TrackingDemoCommand('worktree:demo {--url=} {--user=} {--languages=*} {--sites=*}'));
    Artisan::registerCommand(new TrackingDemoCommand('blog:demo {--url=} {--user=} {--languages=*} {--sites=*}'));
    Artisan::registerCommand(new TrackingDemoCommand('form-builder:demo {--url=} {--user=} {--languages=*} {--sites=*}'));

    test()->artisan('capell:demo', [
        '--url' => 'https://example.test',
        '--packages' => 'capell-app/worktree,capell-app/blog,capell-app/form-builder',
        '--sites' => 'Main Site',
        '--languages' => 'en',
        '--force' => true,
    ])->assertExitCode(0);

    expect(TrackingDemoCommand::$executionOrder)->toBe([
        'form-builder:demo',
        'blog:demo',
        'worktree:demo',
    ]);
});
