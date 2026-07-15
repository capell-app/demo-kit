# Demo Kit

<!-- prettier-ignore-start -->

## What This Plugin Adds

Demo Kit is an **Available**, **No schema impact** Capell package in the **Capell Foundation** product group. It ships as `capell-app/demo-kit` and extends these surfaces: admin, frontend, console.

Demo Kit creates deterministic demo users, sites, languages, pages, media, and package examples from a repeatable generation plan.

Administrators can start demo generation from a package page or console command, then inspect the generated pages and components on the frontend.

Evidence: [`capell.json`](capell.json), [`src/Actions/BuildDemoGenerationPlanAction.php`](src/Actions/BuildDemoGenerationPlanAction.php), [`src/Support/Creator/DemoCreator.php`](src/Support/Creator/DemoCreator.php), [`src/Providers/DemoKitServiceProvider.php`](src/Providers/DemoKitServiceProvider.php), [`docs/overview.admin.md`](docs/overview.admin.md), [`docs/screenshots.json`](docs/screenshots.json), [`src/Filament/Pages/DemoKitPage.php`](src/Filament/Pages/DemoKitPage.php), [`tests/Feature/Commands/FullDemoCommandTest.php`](tests/Feature/Commands/FullDemoCommandTest.php).

Status details:

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/demo-kit`
- Namespace: `Capell\DemoKit`
- Theme key: not applicable

## Why It Matters

**For developers:** Typed generation plans and package demo command orchestration make local setup, screenshots, and QA data reproducible from a chosen seed.

**For teams:** Teams can evaluate a populated site before entering real content and reset the sample state between demos, with explicit guards against production seeding.

Evidence: [`src/Data/DemoGenerationPlanData.php`](src/Data/DemoGenerationPlanData.php), [`src/Actions/BuildDemoGenerationPlanAction.php`](src/Actions/BuildDemoGenerationPlanAction.php), [`src/Console/Commands/FullDemoCommand.php`](src/Console/Commands/FullDemoCommand.php), [`tests/Unit/Actions/BuildDemoGenerationPlanActionTest.php`](tests/Unit/Actions/BuildDemoGenerationPlanActionTest.php), [`docs/overview.admin.md`](docs/overview.admin.md), [`src/Actions/ResetDemoSitesAction.php`](src/Actions/ResetDemoSitesAction.php), [`src/Console/Commands/Concerns/GuardsAgainstProduction.php`](src/Console/Commands/Concerns/GuardsAgainstProduction.php), [`tests/Feature/Commands/AdminDemoCommandTest.php`](tests/Feature/Commands/AdminDemoCommandTest.php).

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

![Demo Kit admin page](docs/screenshots/demo-kit-admin-page.png)

![Generated demo page content widget](docs/screenshots/demo-page-content-widget.png)

- Demo Kit admin page (admin, required).
- Insights consent priming capture (frontend, optional).
- Generated demo page content widget (frontend, required).
- Generated homepage section widget (frontend, required).

## Technical Shape

- Service providers: `Capell\DemoKit\Providers\DemoKitServiceProvider`.
- Config files: `packages/demo-kit/config/capell-demo-kit.php`.
- Filament classes: `HomepageSectionWidgetConfigurator`, `DemoKitPage`.
- Livewire components: `KitchenSinkStressWidget`, `ResourcesLibrary`.
- Actions: `BuildDemoGenerationPlanAction`, `BuildDemoPageContentViewDataAction`, `BuildKitchenSinkLayoutWidgetEntriesAction`, `ConfigureKitchenSinkReferenceWidgetsAction`, `CreateDemoLanguagesAction`, `CreateDemoSiteAction`, `CreateDemoUsersAction`, `CreateKitchenSinkContextPagesAction`, `CreateKitchenSinkSourceWidgetsAction`, `CreateKitchenSinkVariantWidgetsAction`, `AssertDefaultDemoInstallHealthAction`, `DemoInstallHealthData`, `and 9 more`.
- Data objects: `DemoGenerationPlanData`, `DemoPageContentViewData`, `DemoPagePlanData`, `DemoProfileData`, `DemoSiteGenerationPlanData`.
- Command signatures: `capell:demo-kit-doctor`, `capell:demo-kit-full-demo`.
- Console command classes: `AdminDemoCommand`, `GuardsAgainstProduction`, `HasLanguagesOption`, `HasSitesOption`, `DemoCommand`, `DemoKitDoctorCommand`, `FullDemoCommand`, `KitchenSinkDemoCommand`, `RefreshDemoStitchPagesCommand`.
- Manifest contributions: `admin-page: Capell\DemoKit\Manifest\DemoKitAdminPageContribution`, `asset: Capell\DemoKit\Manifest\DemoKitAssetsContribution`, `configurator: Capell\DemoKit\Manifest\DemoKitConfiguratorContribution`, `console-command: Capell\DemoKit\Manifest\DemoKitConsoleCommandsContribution`, `dashboard-widget: Capell\DemoKit\Manifest\DemoKitRenderablesContribution`, `frontend-component: Capell\DemoKit\Manifest\DemoKitFrontendComponentsContribution`, `health-check: Capell\DemoKit\Health\DemoKitHealthCheck`.
- Health checks: `Capell\DemoKit\Health\DemoKitHealthCheck`.
- Blade views: `packages/demo-kit/resources/views/components/widget/demo-page-content-assets.blade.php`, `packages/demo-kit/resources/views/components/widget/demo-page-content.blade.php`, `packages/demo-kit/resources/views/components/widget/homepage-section.blade.php`, `packages/demo-kit/resources/views/filament/pages/demo-kit.blade.php`, `packages/demo-kit/resources/views/livewire/kitchen-sink-stress-widget.blade.php`, `packages/demo-kit/resources/views/livewire/resources-library.blade.php`.
- Cache tags: `demo-kit`.

## Data Model

This package has no schema impact. It extends Capell through `admin-page` contributions, `asset` contributions, `configurator` contributions, `console-command` contributions, `dashboard-widget` contributions, `frontend-component` contributions, and `health-check` contributions instead of declaring package-owned tables.

## Install Impact

- Required packages: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`, `capell-app/layout-builder`.
- Admin navigation: declares `admin-page: DemoKitAdminPageContribution`; each Filament page or resource controls its own navigation visibility.
- Admin/editor extensions: `configurator: DemoKitConfiguratorContribution`, `dashboard-widget: DemoKitRenderablesContribution`.
- Permissions: none declared in `capell.json`.
- Public routes: none declared.
- Database changes: no package migrations declared.
- Config: `config/capell-demo-kit.php`.
- Settings: no package settings declared.
- Queues or schedules: none declared.
- Cache tags: `demo-kit`.
- Commands: `capell:demo-kit-doctor`, `capell:demo-kit-full-demo`.

## Common Pitfalls

- Keep required Capell packages on compatible v4 releases: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`, `capell-app/layout-builder`.
- Review package configuration before production-like verification: `config/capell-demo-kit.php`.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Custom write integrations must preserve invalidation for `demo-kit` cache tags.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. Install the package: `composer require capell-app/demo-kit`.
2. Review `config/capell-demo-kit.php` before enabling the package.
3. Open the Demo Kit admin page and confirm the admin workflow loads.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- Configuration files: [`config/capell-demo-kit.php`](config/capell-demo-kit.php).
- [Troubleshooting](#troubleshooting)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Related packages: [Layout Builder](../layout-builder/README.md).
- Focused tests: `vendor/bin/pest packages/demo-kit/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
