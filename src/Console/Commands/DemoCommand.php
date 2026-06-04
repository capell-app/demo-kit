<?php

declare(strict_types=1);

namespace Capell\DemoKit\Console\Commands;

use Capell\Core\Actions\DemoPackageAction;
use Capell\Core\Console\Commands\Concerns\HasPackageSelection;
use Capell\Core\Console\Commands\Concerns\PromptsWithOptionFallback;
use Capell\Core\Data\PackageData;
use Capell\DemoKit\Console\Commands\Concerns\GuardsAgainstProduction;
use Capell\DemoKit\Console\Commands\Concerns\HasLanguagesOption;
use Capell\DemoKit\Console\Commands\Concerns\HasSitesOption;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class DemoCommand extends Command
{
    use GuardsAgainstProduction;
    use HasLanguagesOption;
    use HasPackageSelection;
    use HasSitesOption;
    use PromptsWithOptionFallback;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'capell:demo {--user=} {--languages=} {--packages} {--seed=} {--sites=} {--url} {--allow-production} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add example site content to the sites.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->passesProductionGuard()) {
            return Command::FAILURE;
        }

        if (! $this->option('force')
            && $this->input->isInteractive()
            && ! confirm('Are you sure you want to install example site content?', false)
        ) {
            $this->info('Installation cancelled.');

            return Command::FAILURE;
        }

        if (! $this->option('force') && ! $this->input->isInteractive()) {
            $this->error('Installing example site content requires --force in non-interactive mode.');

            return Command::FAILURE;
        }

        $siteUrl = $this->getSiteUrl();

        $user = $this->resolveUserOption();
        $seed = $this->resolveSeedOption();

        $packages = $this->getSelectedPackages();

        if ($packages->isEmpty()) {
            $this->warn('No packages selected. Exiting command.');

            return Command::FAILURE;
        }

        if ($this->option('sites') !== null) {
            $siteOptions = is_string($this->option('sites'))
                ? explode(',', $this->option('sites'))
                : (is_array($this->option('sites')) ? $this->option('sites') : null);
        } else {
            $siteOptions = $this->getDemoSites();
        }

        if ($this->hasOption('languages') && $this->option('languages') !== null) {
            $languages = is_string($this->option('languages'))
                ? explode(',', $this->option('languages'))
                : (is_array($this->option('languages')) ? $this->option('languages') : null);
        } else {
            $languages = $this->getDemoLanguages();
        }

        $this->comment(str_repeat('-', 40));
        $this->comment('Installing demo data');
        $this->newLine();

        $this->installDemoPackages($packages, $siteUrl, $user, $seed, $languages, $siteOptions);

        $this->newLine();
        $this->info('Finished installing demo data.');

        return Command::SUCCESS;
    }

    /**
     * Resolve the author identifier (email or id) supplied via --user, if any.
     */
    private function resolveUserOption(): ?string
    {
        $user = $this->option('user');

        if (is_scalar($user) && (string) $user !== '') {
            return (string) $user;
        }

        return null;
    }

    private function resolveSeedOption(): ?int
    {
        $seed = $this->option('seed');

        return is_scalar($seed) && (string) $seed !== '' ? (int) $seed : null;
    }

    /**
     * Get the site URL from option or prompt.
     */
    private function getSiteUrl(): string
    {
        $siteUrl = $this->option('url');

        if (is_scalar($siteUrl) && (string) $siteUrl !== '') {
            return (string) $siteUrl;
        }

        $this->requireInteractiveOrFail('Site URL', 'Pass --url=<url>.');

        return text(
            label: 'What is the URL of the site?',
            default: config('app.url'),
            required: true,
            validate: ['siteUrl' => 'required|url'],
        );
    }

    /**
     * Install demo data for selected packages.
     *
     * @param  Collection<array-key, mixed>  $packages
     * @param  array<array-key, mixed>|null  $languages
     * @param  array<array-key, mixed>|null  $sites
     */
    private function installDemoPackages(Collection $packages, string $siteUrl, ?string $user, ?int $seed, ?array $languages, ?array $sites): void
    {
        $packages->each(function (PackageData $package) use ($siteUrl, $user, $seed, $languages, $sites): void {
            if ($package->name === DemoKitServiceProvider::$packageName) {
                return;
            }

            $this->comment(sprintf('Installing %s demo...', $package->name));

            if (in_array($package->getDemoCommand(), [null, '', '0'], true)) {
                return;
            }

            $this->comment('Running command: ' . $package->getDemoCommand());
            $params = [];

            if (in_array('url', $package->getDemoParams(), true)) {
                $params['--url'] = $siteUrl;
            }

            if ($user !== null && in_array('user', $package->getDemoParams(), true)) {
                $params['--user'] = $user;
            }

            if ($seed !== null && in_array('seed', $package->getDemoParams(), true)) {
                $params['--seed'] = $seed;
            }

            if (in_array('languages', $package->getDemoParams(), true) && is_array($languages) && $languages !== []) {
                $params['--languages'] = $languages;
            }

            if (in_array('sites', $package->getDemoParams(), true) && is_array($sites) && $sites !== []) {
                $params['--sites'] = $sites;
            }

            DemoPackageAction::run($package, $params);

            $this->comment('Successfully setup demo: ' . $package->name);
            $this->newLine();
        });
    }
}
