<?php

declare(strict_types=1);

namespace Capell\DemoKit\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Filament\Pages\ExtensionsPage;
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
use Capell\DemoKit\Support\KitchenSinkPublicLayoutWidgetPayloadContributor;
use Capell\LayoutBuilder\Contracts\PublicLayoutWidgetPayloadContributor;
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
            ->hasAssets()
            ->hasConfigFile('capell-demo-kit')
            ->hasMigration('2026_07_19_130000_create_demo_kit_generation_runs_table')
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
        parent::registeringPackage();

        if (CapellCore::hasPackage(self::$packageName)) {
            $package = CapellCore::getPackage(self::$packageName);
            $package->setupParams = ['url', 'user', 'languages', 'sites', 'site-count', 'page-count', 'packages', 'theme', 'seed', 'quick', 'reset', 'skip-demo-users', 'allow-production', 'force'];
            $package->demoCommand = 'capell:demo-kit-full-demo';
            $package->demoParams = ['url', 'user', 'languages', 'sites', 'site-count', 'page-count', 'packages', 'theme', 'seed', 'quick', 'reset', 'skip-demo-users', 'allow-production', 'adopt-existing-site', 'skip-package-demos', 'force'];
        }

        $this->registerAdminPanelExtensions();
        $this->registerPublicLayoutWidgetPayloadContributors();
    }

    public function packageBooted(): void
    {
        $this->registerLaravelAssetPublishGroup();
        $this->registerPackageLivewireComponents();
        $this->registerTailwindSources();
        $this->registerRenderables();
        $this->registerPresentationMode();
        $this->registerAdminPanelExtensions();
    }

    private function registerLaravelAssetPublishGroup(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../../resources/dist' => public_path('vendor/' . self::$name),
        ], 'laravel-assets');
    }

    private function registerPackageLivewireComponents(): void
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

    private function registerPublicLayoutWidgetPayloadContributors(): void
    {
        if (! interface_exists(PublicLayoutWidgetPayloadContributor::class)) {
            return;
        }

        $this->app->tag([KitchenSinkPublicLayoutWidgetPayloadContributor::class], PublicLayoutWidgetPayloadContributor::TAG);
    }

    private function registerAdminPanelExtensions(): void
    {
        $this->registerExtensionPageRegistry();
        $this->registerAdminSurfacePage();
        $this->registerConfigurators();
    }

    private function registerPresentationMode(): void
    {
        if (config('capell-demo-kit.presentation_mode', true)) {
            config()->set('capell-welcome-tour.presentation_mode', true);

            CapellAdmin::registerWelcomeTourStep(
                key: 'capell-demo-kit.extensions',
                title: fn (): string => __('capell-demo-kit::page.tour_extensions_title'),
                description: fn (): string => __('capell-demo-kit::page.tour_extensions_description'),
                element: '[data-tour-id="welcome-tour-extensions"]',
                icon: 'heroicon-o-squares-plus',
                iconColor: 'info',
                sort: 65,
                visible: fn (): bool => ExtensionsPage::canAccess(),
                chapter: 'extensions',
                route: '/admin/extensions',
            );
        }
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
