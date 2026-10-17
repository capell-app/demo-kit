<?php

declare(strict_types=1);

use Capell\Core\Actions\InstallPackageAction;
use Capell\Core\Actions\RuntimeRefresh\RefreshInstalledPackageRuntimeAction;
use Capell\Core\Facades\CapellCore;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

it('publishes demo assets after metadata preload and late installation', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $root = dirname(__DIR__, 3);
        $manifest = InstalledRuntimeParity::manifest('demo-kit');
        $directory = sys_get_temp_dir() . '/demo-late-assets-' . bin2hex(random_bytes(8));
        $files = new Filesystem;

        try {
            // Discard publications from the enclosing installed package fixture.
            ServiceProvider::$publishes = [];
            ServiceProvider::$publishGroups = [];
            $app = InstalledRuntimeTestCase::boot(
                $manifest,
                false,
                configureBoot: static function (Application $app) use ($directory): void {
                    $app->usePublicPath($directory);
                },
                sourceRoot: $root,
                packageRoot: dirname($root),
            );

            expect(CapellCore::isPackageInstalled($manifest['name']))->toBeFalse()
                ->and(ServiceProvider::pathsToPublish(DemoKitServiceProvider::class, 'laravel-assets'))->toBe([]);

            InstalledRuntimeTestCase::prepareLateInstallMigrations($app);
            InstallPackageAction::run(CapellCore::getPackage($manifest['name']));

            expect(CapellCore::isPackageInstalled($manifest['name']))->toBeTrue();
            $paths = ServiceProvider::pathsToPublish(DemoKitServiceProvider::class, 'laravel-assets');
            expect($paths)->toHaveCount(1);

            RefreshInstalledPackageRuntimeAction::run(CapellCore::getPackage($manifest['name']));
            RefreshInstalledPackageRuntimeAction::run(CapellCore::getPackage($manifest['name']));
            expect(ServiceProvider::pathsToPublish(DemoKitServiceProvider::class, 'laravel-assets'))->toBe($paths);

            expect($app->make(Kernel::class)->call('vendor:publish', [
                '--provider' => DemoKitServiceProvider::class,
                '--tag' => 'laravel-assets',
                '--force' => true,
            ]))->toBe(0);

            $sources = $files->allFiles($root . '/resources/dist');
            expect($sources)->not->toBeEmpty();
            foreach ($sources as $source) {
                $published = $directory . '/vendor/capell-demo-kit/' . $source->getRelativePathname();
                expect($published)->toBeFile()
                    ->and(hash_file('sha256', $published))->toBe(hash_file('sha256', $source->getPathname()));
            }
        } finally {
            $files->deleteDirectory($directory);
        }
    });
});
