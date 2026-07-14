<?php

declare(strict_types=1);

namespace Capell\DemoKit\Console\Commands;

use Capell\Core\Data\PackageData;
use Capell\Core\Facades\CapellCore;
use Capell\DemoKit\Actions\BuildDemoGenerationPlanAction;
use Capell\DemoKit\Actions\InstallKitchenSinkDemoPageAction;
use Capell\DemoKit\Console\Commands\Concerns\GuardsAgainstProduction;
use Capell\DemoKit\Data\DemoSiteGenerationPlanData;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class FullDemoCommand extends Command
{
    use GuardsAgainstProduction;

    protected $signature = 'capell:demo-kit-full-demo
        {--url=}
        {--user=}
        {--languages=}
        {--sites=}
        {--site-count=}
        {--page-count=}
        {--packages=}
        {--theme=}
        {--seed=}
        {--quick}
        {--reset}
        {--adopt-existing-site}
        {--skip-demo-users}
        {--allow-production}
        {--force}';

    protected $description = 'Create full multi-site and multi-language example data.';

    public function handle(): int
    {
        if (! $this->passesProductionGuard()) {
            return Command::FAILURE;
        }

        if (! $this->option('force') && ! $this->input->isInteractive()) {
            $this->error('Creating full example site data requires --force in non-interactive mode.');

            return Command::FAILURE;
        }

        $queueConversionsByDefault = config('media-library.queue_conversions_by_default');
        config(['media-library.queue_conversions_by_default' => false]);

        try {
            return $this->createFullDemo();
        } finally {
            config(['media-library.queue_conversions_by_default' => $queueConversionsByDefault]);
        }
    }

    private function createFullDemo(): int
    {
        $url = $this->resolveUrl();
        /** @var array{sites?: list<string>, site_count?: int, pages?: int, languages?: list<string>, seed?: int|null} $options */
        $options = [
            'sites' => $this->parseCsvOption('sites'),
            'languages' => $this->resolveLanguages(),
            'seed' => $this->resolveSeedOption(),
        ];

        $siteCount = $this->resolvePositiveIntegerOption('site-count');
        if ($siteCount !== null) {
            $options['site_count'] = $siteCount;
        } elseif ($this->option('quick') === true && $this->parseCsvOption('sites') === []) {
            $options['site_count'] = 1;
        }

        $pageCount = $this->resolvePositiveIntegerOption('page-count');
        $adminPageCount = $pageCount;
        if ($adminPageCount !== null) {
            $options['pages'] = $adminPageCount;
        } elseif ($this->option('quick') === true) {
            $adminPageCount = 3;
            $options['pages'] = $adminPageCount;
        }

        $plan = BuildDemoGenerationPlanAction::run($options);
        $languages = $plan->languageCodes;
        $sites = array_map(
            static fn (DemoSiteGenerationPlanData $site): string => $site->name,
            $plan->sites,
        );

        $this->info('Creating full example sites and languages.');

        $user = $this->resolveUserOption();

        $adminDemoParams = [
            '--url' => $url,
            '--languages' => implode(',', $languages),
            '--sites' => implode(',', $sites),
        ];

        if ($adminPageCount !== null) {
            $adminDemoParams['--page-count'] = $adminPageCount;
        }

        if ($plan->seed !== null) {
            $adminDemoParams['--seed'] = $plan->seed;
        }

        if ($this->option('reset') === true) {
            $adminDemoParams['--reset'] = true;
        }

        if ($this->option('adopt-existing-site') === true) {
            $adminDemoParams['--adopt-existing-site'] = true;
        }

        if ($this->option('skip-demo-users') === true) {
            $adminDemoParams['--skip-demo-users'] = true;
        }

        if ($user !== null) {
            $adminDemoParams['--user'] = $user;
        }

        if ($this->option('allow-production') === true) {
            $adminDemoParams['--allow-production'] = true;
        }

        $adminDemoExitCode = $this->call('capell:admin-demo', $adminDemoParams);

        if ($adminDemoExitCode !== Command::SUCCESS) {
            return $adminDemoExitCode;
        }

        $packageNames = $this->demoPackageNames();

        if ($packageNames !== []) {
            $packageDemoParams = [
                '--url' => $url,
                '--languages' => implode(',', $languages),
                '--sites' => implode(',', $sites),
                '--packages' => implode(',', $packageNames),
                '--force' => true,
            ];

            if ($user !== null) {
                $packageDemoParams['--user'] = $user;
            }

            if ($plan->seed !== null) {
                $packageDemoParams['--seed'] = $plan->seed;
            }

            if ($this->option('allow-production') === true) {
                $packageDemoParams['--allow-production'] = true;
            }

            $packageDemoExitCode = $this->call('capell:demo', $packageDemoParams);

            if ($packageDemoExitCode !== Command::SUCCESS) {
                return $packageDemoExitCode;
            }
        }

        InstallKitchenSinkDemoPageAction::run();

        $this->info('Full example site data created successfully.');

        return Command::SUCCESS;
    }

    /**
     * Resolve the chosen author identifier so it can be forwarded verbatim to
     * both capell:admin-demo and capell:demo, ensuring package-contributed demo
     * content is attributed to the same author.
     */
    private function resolveUserOption(): ?string
    {
        $user = $this->option('user');

        if (is_scalar($user) && (string) $user !== '') {
            return (string) $user;
        }

        return null;
    }

    private function resolveUrl(): string
    {
        $url = $this->option('url');

        if (is_string($url) && $url !== '') {
            return $url;
        }

        return (string) config('app.url');
    }

    /**
     * @return list<string>
     */
    private function resolveLanguages(): array
    {
        $languages = $this->parseCsvOption('languages');

        if ($languages !== []) {
            return $languages;
        }

        if ($this->option('quick') === true) {
            return ['en'];
        }

        return ['all'];
    }

    /**
     * @return list<string>
     */
    private function parseCsvOption(string $option): array
    {
        $value = $this->option($option);

        if (is_array($value)) {
            return array_values(array_filter(
                array_map(static fn (mixed $item): string => trim((string) $item), $value),
                static fn (string $item): bool => $item !== '',
            ));
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }

    private function resolvePositiveIntegerOption(string $option): ?int
    {
        $value = $this->option($option);

        if (! is_scalar($value) || in_array((string) $value, ['', '0'], true)) {
            return null;
        }

        if (! ctype_digit((string) $value)) {
            throw new InvalidArgumentException(sprintf('The --%s option must be a positive integer.', $option));
        }

        $maximum = $option === 'site-count'
            ? BuildDemoGenerationPlanAction::MAX_SITE_COUNT
            : BuildDemoGenerationPlanAction::MAX_PAGE_COUNT;

        return min((int) $value, $maximum);
    }

    private function resolveSeedOption(): ?int
    {
        $seed = $this->option('seed');

        return is_scalar($seed) && (string) $seed !== '' ? (int) $seed : null;
    }

    private function resolveThemeOption(): ?string
    {
        $theme = $this->option('theme');

        if (! is_scalar($theme) || (string) $theme === '') {
            return null;
        }

        return (string) $theme;
    }

    /**
     * @return list<string>
     */
    private function demoPackageNames(): array
    {
        $selectedPackageNames = $this->parseCsvOption('packages');
        $selectedPackages = $selectedPackageNames === [] ? null : array_fill_keys($selectedPackageNames, true);
        $selectedThemeKey = $this->resolveThemeOption();

        /** @var Collection<string, PackageData> $packages */
        $packages = CapellCore::getInstalledPackages();

        return array_values($packages
            ->reject(fn (PackageData $package): bool => $package->name === DemoKitServiceProvider::$packageName)
            ->when(
                $selectedPackages !== null,
                fn (Collection $packages): Collection => $packages->filter(
                    static fn (PackageData $package): bool => isset($selectedPackages[$package->name]),
                ),
            )
            ->when(
                $selectedThemeKey !== null,
                fn (Collection $packages): Collection => $packages->filter(
                    static fn (PackageData $package): bool => $package->getThemeKey() === null
                        || $package->getThemeKey() === $selectedThemeKey,
                ),
            )
            ->reject(fn (PackageData $package): bool => in_array($package->getDemoCommand(), [null, '', '0'], true))
            ->keys()
            ->values()
            ->all());
    }
}
