<?php

declare(strict_types=1);

namespace Capell\DemoKit\Console\Commands\Concerns;

use Illuminate\Console\Command;

/**
 * Refuses to run demo-seeding commands outside the local and testing
 * environments unless the operator explicitly opts in with --allow-non-local.
 *
 * Demo seeding provisions sites, pages, media and known-credential users; a
 * stray run against a staging or production database would be damaging, so the
 * guard fails closed by default and matches the Demo Kit admin page, which is
 * also local and testing only. --allow-production is kept as an older alias
 * for --allow-non-local.
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
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        if ($this->option('allow-non-local') === true || $this->option('allow-production') === true) {
            $this->warn(sprintf('Running demo seeding in the "%s" environment because --allow-non-local was supplied.', app()->environment()));

            return true;
        }

        $this->error(sprintf('Demo seeding is blocked in the "%s" environment; it only runs in local and testing. Pass --allow-non-local to override (for example for a staging demo).', app()->environment()));

        return false;
    }
}
