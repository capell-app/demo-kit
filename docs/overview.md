# Demo Kit

<!-- prettier-ignore-start -->

## What This Plugin Adds

Demo Kit is an **Available**, **No schema impact** Capell package in the **Capell Foundation** product group. It ships as `capell-app/demo-kit` and extends these surfaces: admin, frontend, console.

Deterministic demo-data orchestration for Capell - seeds users, sites, languages, pages, media and a Foundation showcase homepage, and dispatches per-package demo commands.

After install, admins get package-owned management surfaces and public users may see package-owned frontend output or routes.

Status details:

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/demo-kit`
- Namespace: `Capell\DemoKit`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, Data objects, Filament classes, and Blade views instead of pushing this behaviour into core or application code.

**For teams:** The demo engine for Capell: deterministic, multi-site, multi-language sample content and media. Generates a curated showcase site, fans out to each installed package's own demo command, and ships a health doctor so every demo, screenshot, and QA run starts from known data.

## Screens And Workflow

Screenshot contract: `screenshots.json`.

- Demo Kit admin page (admin, required).
- Insights consent priming capture (frontend, optional).
- Generated demo page content widget (frontend, required).
- Generated homepage section widget (frontend, required).

## Technical Shape

- Service providers: `Capell\DemoKit\Providers\DemoKitServiceProvider`.
- Config files: `packages/demo-kit/config/capell-demo-kit.php`.
- Filament classes: `HomepageSectionWidgetConfigurator`, `DemoKitPage`.
- Livewire components: `KitchenSinkStressWidget`, `ResourcesLibrary`.
- Actions: `BuildDemoGenerationPlanAction`, `BuildDemoPageContentViewDataAction`, `CreateDemoLanguagesAction`, `CreateDemoUsersAction`, `AssertDefaultDemoInstallHealthAction`, `DemoInstallHealthData`, `DummyContentGeneratorAction`, `InsertExampleSiteDataAction`, `InstallKitchenSinkDemoPageAction`, `RedactDemoKitErrorMessageAction`, `RefreshDemoStitchPagesAction`, `ResetDemoSitesAction`.
- Data objects: `DemoGenerationPlanData`, `DemoPageContentViewData`, `DemoPagePlanData`, `DemoProfileData`, `DemoSiteGenerationPlanData`.
- Command signatures: `capell:demo-kit-doctor`, `capell:demo-kit-full-demo`.
- Console command classes: `AdminDemoCommand`, `GuardsAgainstProduction`, `HasLanguagesOption`, `HasSitesOption`, `DemoCommand`, `DemoKitDoctorCommand`, `FullDemoCommand`, `KitchenSinkDemoCommand`, `RefreshDemoStitchPagesCommand`.
- Manifest contributions: `admin-page: Capell\DemoKit\Manifest\DemoKitAdminPageContribution`, `asset: Capell\DemoKit\Manifest\DemoKitAssetsContribution`, `configurator: Capell\DemoKit\Manifest\DemoKitConfiguratorContribution`, `console-command: Capell\DemoKit\Manifest\DemoKitConsoleCommandsContribution`, `dashboard-widget: Capell\DemoKit\Manifest\DemoKitRenderablesContribution`, `frontend-component: Capell\DemoKit\Manifest\DemoKitFrontendComponentsContribution`, `health-check: Capell\DemoKit\Health\DemoKitHealthCheck`.
- Health checks: `Capell\DemoKit\Health\DemoKitHealthCheck`.
- Blade views: `packages/demo-kit/resources/views/components/widget/demo-page-content-assets.blade.php`, `packages/demo-kit/resources/views/components/widget/demo-page-content.blade.php`, `packages/demo-kit/resources/views/components/widget/homepage-section.blade.php`, `packages/demo-kit/resources/views/filament/pages/demo-kit.blade.php`, `packages/demo-kit/resources/views/livewire/kitchen-sink-stress-widget.blade.php`, `packages/demo-kit/resources/views/livewire/resources-library.blade.php`.
- Cache tags: `demo-kit`.

## Data Model

This package has no schema impact. It does not declare package-owned migrations or required tables.

Docs gap: document extension points here if the package delegates persistence to a host package.

## Install Impact

- Admin navigation: adds package-owned Filament classes when registered.
- Permissions: none declared in `capell.json`.
- Public routes: none detected in package route files.
- Database changes: no package migrations declared.
- Settings: no package settings declared.
- Queues or schedules: none detected in standard package paths.
- Cache tags: `demo-kit`.
- Commands: `capell:demo-kit-doctor`, `capell:demo-kit-full-demo`.

## Common Pitfalls

- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Run package commands from the host app; in this repository use `vendor/bin/pest` for package tests.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Background work does not run | Queue worker or scheduled command is not active | Check package jobs, commands, and host scheduler configuration | Start the queue or scheduler, then run the focused command or package test |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. Install the package: `composer require capell-app/demo-kit`.
2. Run the required setup: `php artisan capell:demo-kit-full-demo`.
3. Open the related Capell admin surface and verify Demo Kit appears.

## Next Steps

- [Package docs index](README.md)
- [Screenshot contract](screenshots.json)
- [Marketplace assets](assets/marketplace/)
- [Capell content language plan](../../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../../docs/erd/capell-and-package-erds.md)
- Focused tests: `vendor/bin/pest packages/demo-kit/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
