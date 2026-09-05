<?php

declare(strict_types=1);

namespace Capell\DemoKit\Console\Commands;

use Capell\DemoKit\Actions\InstallKitchenSinkDemoPageAction;
use Capell\DemoKit\Console\Commands\Concerns\GuardsAgainstProduction;
use Illuminate\Console\Command;

final class KitchenSinkDemoCommand extends Command
{
    use GuardsAgainstProduction;

    protected $signature = 'capell:demo-kit-kitchen-sink
        {--allow-production}';

    protected $description = 'Install the Kitchen Sink Demo Page CMS reference fixture.';

    public function handle(): int
    {
        if (! $this->passesProductionGuard()) {
            return Command::FAILURE;
        }

        $page = InstallKitchenSinkDemoPageAction::run();

        $this->info(sprintf('Installed Kitchen Sink Demo Page: %s', $page->name));

        return Command::SUCCESS;
    }
}
