<?php

declare(strict_types=1);

namespace Capell\DemoKit\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Support\CapellAdminManager;
use Capell\Admin\Support\Extensions\ExtensionPageRegistry;
use Capell\Core\Data\RenderableDefinitionData;
use Capell\Core\Data\VendorAssetData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Core\Support\Renderables\RenderableRegistry;
use Capell\DemoKit\Console\Commands\AdminDemoCommand;
use Capell\DemoKit\Console\Commands\DemoCommand;
use Capell\DemoKit\Console\Commands\DemoKitDoctorCommand;
use Capell\DemoKit\Console\Commands\FullDemoCommand;
use Capell\DemoKit\Console\Commands\KitchenSinkDemoCommand;
use Capell\DemoKit\Console\Commands\RefreshDemoStitchPagesCommand;
use Capell\DemoKit\Filament\Configurators\Widgets\HomepageSectionWidgetConfigurator;
use Capell\DemoKit\Filament\Pages\DemoKitPage;
use Capell\DemoKit\Livewire\KitchenSinkStressWidget;
use Capell\DemoKit\Livewire\ResourcesLibrary;
use Capell\DemoKit\Support\KitchenSinkPublicWidgetPayloadContributor;
use Capell\LayoutBuilder\Contracts\PublicWidgetPayloadContributor;
use Capell\LayoutBuilder\Enums\ConfiguratorTypeEnum;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;

final class DemoKitServiceProvider extends AbstractPackageServiceProvider
{
    public const string DemoPageContentRenderable = 'capell.widget.demo-page-content';

    public const string HomepageSectionRenderable = 'capell.widget.homepage-section';

    public const string KitchenSinkLivewireStressRenderable = 'capell-demo-kit.widget.kitchen-sink-livewire-stress';

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
                DemoKitDoctorCommand::class,
                RefreshDemoStitchPagesCommand::class,
                KitchenSinkDemoCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $package = CapellCore::getPackage(self::$packageName);
        $package->setupParams = ['url', 'user', 'languages', 'sites', 'site-count', 'page-count', 'seed', 'force'];
        $package->demoCommand = 'capell:demo-kit-full-demo';
        $package->demoParams = ['url', 'user', 'languages', 'sites', 'site-count', 'page-count', 'seed', 'force'];

        $this->registerAdminPanelExtensions();
        $this->registerPublicWidgetPayloadContributors();
    }

    public function packageBooted(): void
    {
        $this->registerLivewireComponents();
        $this->registerTailwindSources();
        $this->registerRenderables();
        $this->registerAdminPanelExtensions();
    }

    private function registerLivewireComponents(): void
    {
        Livewire::component('capell-demo-kit.resources-library', ResourcesLibrary::class);
        Livewire::component('capell-demo-kit.kitchen-sink-stress-widget', KitchenSinkStressWidget::class);
    }

    private function registerTailwindSources(): void
    {
        CapellCore::registerVendorAsset(
            VendorAssetData::tailwindSource('resources/views/**/*.blade.php', self::$packageName),
        );
    }

    private function registerRenderables(): void
    {
        if (! $this->app->bound(RenderableRegistry::class)) {
            return;
        }

        $this->app->make(RenderableRegistry::class)->register(new RenderableDefinitionData(
            key: self::DemoPageContentRenderable,
            type: 'layout-widget',
            blade: 'capell-demo-kit::widget.demo-page-content',
        ));

        $this->app->make(RenderableRegistry::class)->register(new RenderableDefinitionData(
            key: self::HomepageSectionRenderable,
            type: 'layout-widget',
            blade: 'capell-demo-kit::widget.homepage-section',
        ));

        $this->app->make(RenderableRegistry::class)->register(new RenderableDefinitionData(
            key: self::KitchenSinkLivewireStressRenderable,
            type: 'layout-widget',
            livewire: 'capell-demo-kit.kitchen-sink-stress-widget',
        ));
    }

    private function registerPublicWidgetPayloadContributors(): void
    {
        if (! interface_exists(PublicWidgetPayloadContributor::class)) {
            return;
        }

        $this->app->tag([KitchenSinkPublicWidgetPayloadContributor::class], PublicWidgetPayloadContributor::TAG);
    }

    private function registerAdminPanelExtensions(): void
    {
        $this->registerExtensionPageRegistry();
        $this->registerAdminSurfacePage();
        $this->registerConfigurators();
    }

    private function registerConfigurators(): void
    {
        if (! enum_exists(ConfiguratorTypeEnum::class)) {
            return;
        }

        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::configurator(
            class: HomepageSectionWidgetConfigurator::class,
            group: ConfiguratorTypeEnum::Widget->value,
            name: HomepageSectionWidgetConfigurator::getKey(),
        ));
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

            $capellAdminManager->registerExtensionPage(self::$packageName, DemoKitPage::class);
        };

        if ($this->app->bound(CapellAdminManager::class)) {
            CapellAdmin::registerExtensionPage(self::$packageName, DemoKitPage::class);

            return;
        }

        $this->app->afterResolving(CapellAdminManager::class, $registerExtensionPage);
    }
}
