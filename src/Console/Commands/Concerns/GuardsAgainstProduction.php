<?php

declare(strict_types=1);

namespace Capell\DemoKit\Console\Commands\Concerns;

use Illuminate\Console\Command;

/**
 * Refuses to run destructive demo-seeding commands against a production
 * environment unless the operator explicitly opts in with --allow-production.
 *
 * Demo seeding provisions sites, pages, media and known-credential users; a
 * stray run against a production database would be catastrophic, so the guard
 * fails closed by default.
 *
 * @mixin Command
 */
trait GuardsAgainstProduction
{
    /**
     * @return bool True when the command is permitted to continue.
     */
    private function passesProductionGuard(): bool
    {
        if (! app()->environment('production')) {
            return true;
        }

        if ($this->option('allow-production') === true) {
            $this->warn('Running demo seeding against a production environment because --allow-production was supplied.');

            return true;
        }

        $this->error('Demo seeding is blocked in the production environment. Pass --allow-production to override (this is almost never what you want).');

        return false;
    }
}
