<?php

declare(strict_types=1);

use Capell\Core\Actions\DemoPackageAction;
use Capell\Core\Actions\Install\InstallPackagesAction;
use Capell\Core\Data\InstallInputData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Site;
use Capell\Core\Support\Install\NullProgressReporter;
use Capell\DemoKit\Console\Commands\DemoCommand as DemoKitCommand;
use Capell\FoundationTheme\Console\Commands\DemoCommand;
use Capell\FoundationTheme\Providers\FoundationThemeServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('preserves explicit Foundation targets languages and URL through the real manifest demo dispatcher', function (string $dispatcher): void {
    $existing = Site::factory()->create(['name' => 'Existing Foundation'])->refresh();
    $untouched = Site::factory()->create(['name' => 'Unselected Site']);
    $package = CapellCore::getPackage(FoundationThemeServiceProvider::$packageName);
    Artisan::registerCommand(resolve(DemoCommand::class));

    // Execute the real serialised child command in this test database, rather
    // than spawning a process with a different in-memory SQLite connection.
    DemoPackageAction::setProcessFactory(fn (array $command): object => new class(array_values($command))
    {
        private int $exitCode = 1;

        /** @param list<string> $command */
        public function __construct(private readonly array $command) {}

        public function setTimeout(?float $timeout): void {}

        public function run(?callable $callback = null): int
        {
            $artisanIndex = array_search(base_path('artisan'), $this->command, true);
            assert(is_int($artisanIndex));
            $commandLine = implode(' ', array_map(escapeshellarg(...), array_slice($this->command, $artisanIndex + 1)));
            $this->exitCode = Artisan::call($commandLine);

            if ($callback !== null) {
                $callback('out', Artisan::output());
            }

            return $this->exitCode;
        }

        public function isSuccessful(): bool
        {
            return $this->exitCode === 0;
        }

        public function getExitCode(): int
        {
            return $this->exitCode;
        }
    });

    try {
        if ($dispatcher === 'Demo Kit') {
            $command = resolve(DemoKitCommand::class);
            $command->setLaravel(app());
            $input = new ArgvInput([
                'artisan',
                '--packages=' . $package->name,
                '--sites=Existing Foundation,Disposable Foundation',
                '--languages=en,fr',
                '--url=https://reviewed-foundation.test',
                '--force',
            ]);
            $input->setInteractive(false);
            expect($command->run($input, new BufferedOutput))->toBe(0);
        } elseif ($dispatcher === 'Core installer') {
            resolve(InstallPackagesAction::class)->demoPackage(
                new InstallInputData(
                    siteUrl: 'https://reviewed-foundation.test',
                    packages: [$package->name],
                    languages: ['de'],
                    demoContent: true,
                    cachesToClear: [],
                    generateSitemap: false,
                    generateStaticSite: false,
                    demoSites: ['Existing Foundation', 'Disposable Foundation'],
                    demoLanguages: ['en', 'fr'],
                    selectedThemeKey: FoundationThemeServiceProvider::THEME_KEY,
                ),
                null,
                $package->name,
                new NullProgressReporter,
            );
        } else {
            expect(Artisan::call('capell:theme-foundation-demo', [
                '--sites' => ['Existing Foundation', 'Disposable Foundation'],
                '--languages' => ['en', 'fr'],
                '--url' => 'https://reviewed-foundation.test',
            ]))->toBe(0);
        }

        $disposable = Site::query()->where('name', 'Disposable Foundation')->sole();
        foreach ([$existing->fresh(), $disposable] as $site) {
            $page = $site->pages()->firstOrFail();
            expect($page->translations()->with('language')->get()->pluck('language.code')->sort()->values()->all())->toBe(['en', 'fr'])
                ->and($site->pages()->count())->toBeGreaterThan(0);
        }

        expect($disposable->siteDomains()->pluck('domain')->unique()->all())->toBe(['reviewed-foundation.test'])
            ->and($disposable->siteDomains()->count())->toBe(2)
            ->and($existing->fresh()->getAttributes())->toBe($existing->getAttributes())
            ->and($existing->siteDomains()->count())->toBe(0)
            ->and($untouched->pages()->count())->toBe(0)
            ->and($untouched->siteDomains()->count())->toBe(0)
            ->and(Site::query()->where('name', 'Existing Foundation,Disposable Foundation')->exists())->toBeFalse();
    } finally {
        DemoPackageAction::resetProcessFactory();
    }
})->with(['Demo Kit', 'Core installer', 'Artisan arrays']);
