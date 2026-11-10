# Demo Kit reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/demo-kit`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Demo Kit is an **Available**, **Schema-owning** Capell package in the **Capell Essentials** product group. It ships as `capell-app/demo-kit` and extends these surfaces: admin, frontend, console.

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/demo-kit`
- Namespace: `Capell\DemoKit`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\DemoKit\Providers\DemoKitServiceProvider`
- `Capell\DemoKit\Providers\MigrationMetadataServiceProvider`
- `Capell\DemoKit\Providers\ConsoleServiceProvider`

### Config files

- `packages/demo-kit/config/capell-demo-kit.php`

### Migrations

- `packages/demo-kit/database/migrations/2026_07_19_130000_create_demo_kit_generation_runs_table.php`
- `packages/demo-kit/database/migrations/2026_09_08_120000_add_review_data_to_demo_kit_generation_runs_table.php`

### Models

- `DemoKitGenerationRun`

### Filament classes

- `HomepageSectionWidgetConfigurator`
- `DemoKitPage`

### Livewire components

- `KitchenSinkStressWidget`
- `ResourcesLibrary`

### Actions

- `BuildDemoGenerationPlanAction`
- `BuildDemoGenerationReviewAction`
- `BuildDemoPageContentViewDataAction`
- `BuildKitchenSinkLayoutWidgetEntriesAction`
- `ConfigureKitchenSinkReferenceWidgetsAction`
- `CreateDemoLanguagesAction`
- `CreateDemoSiteAction`
- `CreateDemoUsersAction`
- `CreateKitchenSinkContextPagesAction`
- `CreateKitchenSinkSourceWidgetsAction`
- `CreateKitchenSinkVariantWidgetsAction`
- `AssertDefaultDemoInstallHealthAction`
- `DemoInstallHealthData`
- `DummyContentGeneratorAction`
- `HasDemoSiteProvenanceAction`
- `InsertExampleSiteDataAction`
- `InstallKitchenSinkDemoPageAction`
- `ListDemoKitProvenanceSitesAction`
- `MarkDemoSiteProvenanceAction`
- `PrepareDemoKitScreenshotFixtureAction`
- `QueueDemoKitGenerationAction`
- `ReclaimStalledDemoKitGenerationRunsAction`
- `RedactDemoKitErrorMessageAction`
- `RefreshDemoStitchPagesAction`
- `ResetDemoKitProvenanceAction`
- `ResetDemoSitesAction`
- `ResolveDemoKitResultAction`
- `RestoreDemoKitScreenshotFixtureAction`
- `SyncKitchenSinkPageAssetsAction`

### Data objects

- `DemoGenerationPlanData`
- `DemoGenerationReviewData`
- `DemoKitScreenshotFixtureData`
- `DemoPageContentViewData`
- `DemoPagePlanData`
- `DemoProfileData`
- `DemoSiteGenerationPlanData`

### Jobs

- `RunDemoKitGenerationJob`

### Command signatures

- `capell:admin-demo`
- `capell:demo`
- `capell:demo-kit-doctor`
- `capell:demo-kit-full-demo`
- `capell:demo-kit-kitchen-sink`
- `capell:demo-kit-refresh-stitch-pages`
- `capell:demo-kit-screenshot-fixture`

### Console command classes

- `AdminDemoCommand`
- `GuardsAgainstProduction`
- `HasLanguagesOption`
- `HasSitesOption`
- `DemoCommand`
- `DemoKitDoctorCommand`
- `DemoKitScreenshotFixtureCommand`
- `FullDemoCommand`
- `KitchenSinkDemoCommand`
- `RefreshDemoStitchPagesCommand`

### Manifest contributions

- `admin-page: Capell\DemoKit\Manifest\DemoKitAdminPageContribution`
- `asset: Capell\DemoKit\Manifest\DemoKitAssetsContribution`
- `configurator: Capell\DemoKit\Manifest\DemoKitConfiguratorContribution`
- `console-command: Capell\DemoKit\Manifest\DemoKitConsoleCommandsContribution`
- `dashboard-widget: Capell\DemoKit\Manifest\DemoKitRenderablesContribution`
- `frontend-component: Capell\DemoKit\Manifest\DemoKitFrontendComponentsContribution`
- `health-check: Capell\DemoKit\Health\DemoKitHealthCheck`

### Health checks

- `Capell\DemoKit\Health\DemoKitHealthCheck`

### Blade views

- `packages/demo-kit/resources/views/components/widget/demo-page-content-assets.blade.php`
- `packages/demo-kit/resources/views/components/widget/demo-page-content.blade.php`
- `packages/demo-kit/resources/views/components/widget/homepage-section.blade.php`
- `packages/demo-kit/resources/views/filament/pages/demo-kit.blade.php`
- `packages/demo-kit/resources/views/livewire/kitchen-sink-stress-widget.blade.php`
- `packages/demo-kit/resources/views/livewire/resources-library.blade.php`

### Cache tags

- `demo-kit`


## Data Model

- Required tables: `capell_demo_kit_generation_runs`.
- Models: `DemoKitGenerationRun`.
- Migration files: `2026_07_19_130000_create_demo_kit_generation_runs_table.php`, `2026_09_08_120000_add_review_data_to_demo_kit_generation_runs_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: Docs gap: migrations and manifest contributions do not prove a cascade, pruning command, or timed retention policy.

<!-- prettier-ignore-end -->
