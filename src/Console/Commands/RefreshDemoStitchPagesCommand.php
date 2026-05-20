<?php

declare(strict_types=1);

namespace Capell\DemoKit\Console\Commands;

use Capell\DemoKit\Actions\RefreshDemoStitchPagesAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class RefreshDemoStitchPagesCommand extends Command
{
    protected $signature = 'capell:demo-kit-refresh-stitch-pages
        {--site= : Restrict the refresh to a site name}
        {--language= : Restrict the refresh to a language code}
        {--force : Confirm that demo pages should be created or updated}';

    protected $description = 'Refresh the Stitch-inspired Demo Kit pages and layouts.';

    public function handle(): int
    {
        try {
            $pages = RefreshDemoStitchPagesAction::run(
                $this->stringOption('site'),
                $this->stringOption('language'),
                (bool) $this->option('force'),
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Refreshed %d Stitch demo pages.', $pages->count()));

        return self::SUCCESS;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        if (! is_scalar($value) || (string) $value === '') {
            return null;
        }

        return (string) $value;
    }
}
