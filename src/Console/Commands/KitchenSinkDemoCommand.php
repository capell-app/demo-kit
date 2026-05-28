<?php

declare(strict_types=1);

namespace Capell\DemoKit\Console\Commands;

use Capell\DemoKit\Actions\InstallKitchenSinkDemoPageAction;
use Illuminate\Console\Command;

final class KitchenSinkDemoCommand extends Command
{
    protected $signature = 'capell:demo-kit-kitchen-sink';

    protected $description = 'Install the Kitchen Sink Demo Page CMS reference fixture.';

    public function handle(): int
    {
        $page = InstallKitchenSinkDemoPageAction::run();

        $this->info(sprintf('Installed Kitchen Sink Demo Page: %s', $page->name));

        return Command::SUCCESS;
    }
}
