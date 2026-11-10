<?php

declare(strict_types=1);

namespace Capell\DemoKit\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {

        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/demo-kit', __DIR__ . '/../../database/migrations', [
                '2026_07_19_130000_create_demo_kit_generation_runs_table' => 'capell_demo_kit_generation_runs',
            ]);
        });
    }
}
