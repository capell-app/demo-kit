<?php

declare(strict_types=1);

namespace Capell\DemoKit\Providers;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Support\CapellAdminManager;
use Capell\Admin\Support\Extensions\ExtensionPageRegistry;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\DemoKit\Console\Commands\AdminDemoCommand;
use Capell\DemoKit\Console\Commands\DemoCommand;
use Capell\DemoKit\Console\Commands\FullDemoCommand;
use Capell\DemoKit\Filament\Pages\DemoKitPage;
use Spatie\LaravelPackageTools\Package;

final class DemoKitServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-demo-kit';

    public static string $packageName = 'capell-app/demo-kit';

    public function configurePackage(Package $package): void
    {
        $package->name(self::$name)
            ->hasConfigFile('capell-demo-kit')
            ->hasViews(self::$name)
            ->hasTranslations()
            ->hasCommands([
                DemoCommand::class,
                AdminDemoCommand::class,
                FullDemoCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $package = CapellCore::getPackage(self::$packageName);
        $package->setupParams = ['url', 'user', 'languages', 'sites', 'force'];
        $package->demoCommand = 'capell:demo-kit-full-demo';
        $package->demoParams = ['url', 'user', 'languages', 'sites', 'force'];

        $this->registerAdminPanelExtensions();
    }

    public function packageBooted(): void
    {
        $this->registerAdminPanelExtensions();
    }

    private function registerAdminPanelExtensions(): void
    {
        $this->registerExtensionPageRegistry();
        $this->registerAdminSurfacePage();
    }

    private function registerExtensionPageRegistry(): void
    {
        if (! class_exists(ExtensionPageRegistry::class)) {
            return;
        }

        $registerExtensionPage = static function (ExtensionPageRegistry $extensionPageRegistry): void {
            $extensionPageRegistry->register(self::$packageName, DemoKitPage::class);
        };

        if ($this->app->bound(ExtensionPageRegistry::class)) {
            $registerExtensionPage($this->app->make(ExtensionPageRegistry::class));

            return;
        }

        $this->app->afterResolving(ExtensionPageRegistry::class, $registerExtensionPage);
    }

    private function registerAdminSurfacePage(): void
    {
        if (! class_exists(CapellAdminManager::class)) {
            return;
        }

        $registerExtensionPage = static function (CapellAdminManager $capellAdminManager): void {
            if (! method_exists($capellAdminManager, 'registerExtensionPage')) {
                return;
            }

            $capellAdminManager->registerExtensionPage(self::$packageName, DemoKitPage::class);
        };

        if ($this->app->bound(CapellAdminManager::class)) {
            CapellAdmin::registerExtensionPage(self::$packageName, DemoKitPage::class);

            return;
        }

        $this->app->afterResolving(CapellAdminManager::class, $registerExtensionPage);
    }
}
