<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions\Diagnostics;

use Capell\Core\Actions\Diagnostics\VerifyFrontendBuildAssetsAction;
use Capell\Core\Actions\Packages\BuildPackageCapabilityGraphAction;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Data\Diagnostics\FrontendBuildAssetVerificationResultData;
use Capell\Core\Enums\PackageCapability;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\DemoKit\Data\DemoProfileData;
use Capell\Frontend\Actions\AssertPublicRenderContractAction;
use Capell\HtmlCache\Actions\BuildHtmlCacheEligibilityReportAction;
use Capell\LayoutBuilder\Models\Widget;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * @method static DemoInstallHealthData run()
 */
final class AssertDefaultDemoInstallHealthAction
{
    use AsObject;

    private const string LAYOUT_BUILDER_ELEMENT_MODEL = Widget::class;

    private DemoProfileData $profile;

    public function handle(): DemoInstallHealthData
    {
        $this->profile = DemoProfileData::default();

        $checks = collect([
            $this->layoutBuilderWidgetModelExists(),
            $this->homepageExists(),
            $this->homepageLayoutHasWidgets(),
            $this->homepageStartsWithHero(),
            $this->homepageUsesShowcaseOrder(),
            $this->minimumWidgetCount(),
            ...($this->hasLayoutBuilderWidgetModel() ? [
                $this->configuredShowcaseWidgetsExist(),
                $this->apWidgetsHaveAssets(),
                $this->placeholderLabelsAreAbsent(),
            ] : []),
            $this->minimumMediaCount(),
            $this->runtimeAssetsExist(),
            $this->capabilityGraphIncludesDemoPackages(),
            $this->cacheEligibilityDiagnosticsAreAvailable(),
            $this->publicRenderContractIsAvailable(),
        ]);

        return new DemoInstallHealthData($checks);
    }

    private function layoutBuilderWidgetModelExists(): DoctorCheckResultData
    {
        if ($this->hasLayoutBuilderWidgetModel()) {
            return new DoctorCheckResultData(
                label: 'Layout Builder demo dependency',
                passed: true,
                message: 'Layout Builder widget model is available.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Layout Builder demo dependency',
            passed: false,
            message: 'Layout Builder is not available to Demo Kit.',
            remediation: 'Install capell-app/layout-builder before running the default demo health check.',
        );
    }

    private function homepageExists(): DoctorCheckResultData
    {
        try {
            $site = Site::query()->default()->with('language')->first() ?? Site::query()->with('language')->first();
            $homepage = $site instanceof Site ? Page::getSiteHomePage($site) : null;
        } catch (Throwable) {
            $homepage = null;
        }

        if (! $homepage instanceof Page) {
            return new DoctorCheckResultData(
                label: 'Default demo homepage exists',
                passed: false,
                message: 'No published homepage was found for the default site.',
                remediation: 'Rerun php artisan capell:install --fresh --demo and confirm the theme demo step completes.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo homepage exists',
            passed: true,
            message: sprintf('Homepage #%d is published.', $homepage->getKey()),
        );
    }

    private function homepageLayoutHasWidgets(): DoctorCheckResultData
    {
        $layout = $this->homepageLayout();
        $widgets = $this->layoutWidgetKeys($layout);

        if ($widgets === []) {
            return new DoctorCheckResultData(
                label: 'Homepage layout has widgets',
                passed: false,
                message: 'The homepage layout does not contain any widget keys.',
                remediation: 'Run the selected theme setup/demo command after package setup has completed.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Homepage layout has widgets',
            passed: true,
            message: sprintf('Homepage layout references %d widget occurrence(s).', count($widgets)),
        );
    }

    private function minimumWidgetCount(): DoctorCheckResultData
    {
        $count = $this->homepageWidgetCount();

        if ($count < $this->profile->minimumWidgetCount) {
            return new DoctorCheckResultData(
                label: 'Default demo widget count',
                passed: false,
                message: sprintf('Homepage has %d widget(s); expected at least %d.', $count, $this->profile->minimumWidgetCount),
                remediation: 'Rerun the demo package step and confirm the demo package runs after setup packages.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo widget count',
            passed: true,
            message: sprintf('Homepage has %d widget(s).', $count),
        );
    }

    private function homepageUsesShowcaseOrder(): DoctorCheckResultData
    {
        $layout = $this->homepageLayout();
        $widgets = $this->layoutWidgetKeys($layout);
        $actual = array_slice($widgets, 0, count($this->profile->showcaseWidgetOrder));

        if ($actual !== $this->profile->showcaseWidgetOrder) {
            return new DoctorCheckResultData(
                label: 'Default demo showcase widget order',
                passed: false,
                message: sprintf(
                    'Homepage starts with [%s]; expected [%s].',
                    implode(', ', $actual),
                    implode(', ', $this->profile->showcaseWidgetOrder),
                ),
                remediation: 'Rerun the demo package step so the curated Foundation showcase homepage layout is rebuilt.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo showcase widget order',
            passed: true,
            message: 'Homepage uses the configured showcase widget order.',
        );
    }

    private function configuredShowcaseWidgetsExist(): DoctorCheckResultData
    {
        $widgetModel = self::LAYOUT_BUILDER_ELEMENT_MODEL;
        $expectedKeys = array_values(array_unique([
            ...$this->profile->homepageOpeningWidgetKeys,
            ...$this->profile->showcaseWidgetOrder,
            ...array_keys($this->profile->widgetAssetMinimums),
        ]));

        $existingKeys = $widgetModel::query()
            ->whereIn('key', $expectedKeys)
            ->pluck('key')
            ->all();

        $missingKeys = array_values(array_diff($expectedKeys, array_filter($existingKeys, is_string(...))));

        if ($missingKeys !== []) {
            return new DoctorCheckResultData(
                label: 'Default demo showcase widget keys',
                passed: false,
                message: sprintf('Missing configured demo widget key(s): %s.', implode(', ', $missingKeys)),
                remediation: 'Update capell-demo-kit health config or rerun the Foundation showcase demo so configured widget keys exist.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo showcase widget keys',
            passed: true,
            message: sprintf('All %d configured showcase widget key(s) exist.', count($expectedKeys)),
        );
    }

    private function apWidgetsHaveAssets(): DoctorCheckResultData
    {
        $widgetModel = self::LAYOUT_BUILDER_ELEMENT_MODEL;

        foreach ($this->profile->widgetAssetMinimums as $widgetKey => $minimum) {
            $widget = $widgetModel::query()
                ->where('key', $widgetKey)
                ->withCount('assets')
                ->first();

            $assetCount = $widget instanceof $widgetModel ? (int) $widget->getAttribute('assets_count') : 0;

            if ($assetCount < $minimum) {
                return new DoctorCheckResultData(
                    label: 'Default demo AP widget assets',
                    passed: false,
                    message: sprintf('Widget "%s" has %d asset(s); expected at least %d.', $widgetKey, $assetCount, $minimum),
                    remediation: 'Rerun the default demo fixtures so AP widgets receive their editable content and media assets.',
                );
            }
        }

        return new DoctorCheckResultData(
            label: 'Default demo AP widget assets',
            passed: true,
            message: 'AP showcase widgets have the expected editable assets.',
        );
    }

    private function homepageStartsWithHero(): DoctorCheckResultData
    {
        $firstWidgetKey = $this->firstHomepageWidgetKey();

        if ($firstWidgetKey === null || ! in_array($firstWidgetKey, $this->profile->homepageOpeningWidgetKeys, true)) {
            return new DoctorCheckResultData(
                label: 'Homepage starts with a hero widget',
                passed: false,
                message: $firstWidgetKey === null
                    ? 'The homepage layout has no first widget.'
                    : sprintf(
                        'The homepage starts with "%s"; expected one of [%s].',
                        $firstWidgetKey,
                        implode(', ', $this->profile->homepageOpeningWidgetKeys),
                    ),
                remediation: 'Rerun the demo package step after the selected theme setup so the homepage layout order is rebuilt.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Homepage starts with a hero widget',
            passed: true,
            message: sprintf('Homepage starts with configured opening widget "%s".', $firstWidgetKey),
        );
    }

    private function minimumMediaCount(): DoctorCheckResultData
    {
        if (! Schema::hasTable('media')) {
            return new DoctorCheckResultData(
                label: 'Default demo media count',
                passed: false,
                message: 'The media table does not exist.',
                remediation: 'Run php artisan migrate and rerun the demo package step.',
            );
        }

        $count = resolve(ConnectionResolverInterface::class)->table('media')->count();

        if ($count < $this->profile->minimumMediaCount) {
            return new DoctorCheckResultData(
                label: 'Default demo media count',
                passed: false,
                message: sprintf('Demo has %d media record(s); expected at least %d.', $count, $this->profile->minimumMediaCount),
                remediation: 'Rerun php artisan capell:install --fresh --demo and confirm media fixtures publish successfully.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo media count',
            passed: true,
            message: sprintf('Demo has %d media record(s).', $count),
        );
    }

    private function placeholderLabelsAreAbsent(): DoctorCheckResultData
    {
        $widgetModel = self::LAYOUT_BUILDER_ELEMENT_MODEL;

        $homepageWidgetIds = $widgetModel::query()
            ->whereIn('key', $this->layoutWidgetKeys($this->homepageLayout()))
            ->pluck('id');

        $found = Translation::query()
            ->where('translatable_type', resolve($widgetModel)->getMorphClass())
            ->whereIn('translatable_id', $homepageWidgetIds)
            ->where(function ($query): void {
                foreach ($this->profile->placeholderLabels as $label) {
                    $query->orWhere('title', 'like', sprintf('%%%s%%', $label))
                        ->orWhere('content', 'like', sprintf('%%%s%%', $label));
                }
            })
            ->exists();

        if ($found) {
            return new DoctorCheckResultData(
                label: 'Default demo placeholder labels',
                passed: false,
                message: 'The demo still contains placeholder or generic homepage labels.',
                remediation: 'Rerun the default demo fixtures and ensure the Foundation showcase copy replaces generic AP/lorem content.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo placeholder labels',
            passed: true,
            message: 'No known placeholder homepage labels were found.',
        );
    }

    private function runtimeAssetsExist(): DoctorCheckResultData
    {
        $failures = VerifyFrontendBuildAssetsAction::run()
            ->reject(fn (FrontendBuildAssetVerificationResultData $result): bool => $result->passed);

        if ($failures->isNotEmpty()) {
            $firstFailure = $failures->first();

            return new DoctorCheckResultData(
                label: 'Required published runtime assets',
                passed: false,
                message: $firstFailure->message,
                remediation: $firstFailure->remediation,
            );
        }

        return new DoctorCheckResultData(
            label: 'Required published runtime assets',
            passed: true,
            message: 'All registered runtime build assets are published.',
        );
    }

    private function capabilityGraphIncludesDemoPackages(): DoctorCheckResultData
    {
        $graph = BuildPackageCapabilityGraphAction::run();

        if ($graph->packageHas('capell-app/frontend', PackageCapability::PublicStatic)
            || $graph->packageHas('capell-app/theme-foundation', PackageCapability::FrontendAssets)) {
            return new DoctorCheckResultData(
                label: 'Default demo package capabilities',
                passed: true,
                message: 'The package capability graph includes frontend demo capabilities.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo package capabilities',
            passed: false,
            message: 'The package capability graph does not include expected frontend demo capabilities.',
            remediation: 'Confirm capell.json manifests are discovered and include typed frontend capabilities.',
        );
    }

    private function cacheEligibilityDiagnosticsAreAvailable(): DoctorCheckResultData
    {
        if (class_exists(BuildHtmlCacheEligibilityReportAction::class)) {
            return new DoctorCheckResultData(
                label: 'Default demo cache eligibility diagnostics',
                passed: true,
                message: 'HTML cache eligibility diagnostics are available for demo routes.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo cache eligibility diagnostics',
            passed: false,
            message: 'HTML cache eligibility diagnostics are not available.',
            remediation: 'Install capell-app/html-cache with the demo package set.',
        );
    }

    private function publicRenderContractIsAvailable(): DoctorCheckResultData
    {
        if (class_exists(AssertPublicRenderContractAction::class)) {
            return new DoctorCheckResultData(
                label: 'Default demo public render contract',
                passed: true,
                message: 'The public render contract can validate demo frontend output.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo public render contract',
            passed: false,
            message: 'The public render contract action is not available.',
            remediation: 'Update capell-app/frontend before running demo parity checks.',
        );
    }

    private function homepageWidgetCount(): int
    {
        $layout = $this->homepageLayout();
        if (! $layout instanceof Layout) {
            return 0;
        }

        return count(array_unique($this->layoutWidgetKeys($layout)));
    }

    private function homepageLayout(): ?Layout
    {
        try {
            $site = Site::query()->default()->with('language')->first() ?? Site::query()->with('language')->first();
            $homepage = $site instanceof Site ? Page::getSiteHomePage($site) : null;

            return $homepage?->layout;
        } catch (Throwable) {
            return null;
        }
    }

    private function firstHomepageWidgetKey(): ?string
    {
        $layout = $this->homepageLayout();
        if (! $layout instanceof Layout) {
            return null;
        }

        foreach ($layout->containers ?? [] as $container) {
            if (! is_array($container)) {
                continue;
            }

            $widgets = $container['widgets'] ?? [];
            if (! is_array($widgets)) {
                continue;
            }

            $widget = $widgets[array_key_first($widgets)] ?? null;

            if (! is_array($widget)) {
                continue;
            }

            $key = (string) ($widget['widget_key'] ?? $widget['key'] ?? '');

            return $key !== '' ? $key : null;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function layoutWidgetKeys(?Layout $layout): array
    {
        if (! $layout instanceof Layout) {
            return [];
        }

        return collect($layout->containers ?? [])
            ->flatMap(function (mixed $container): array {
                if (! is_array($container)) {
                    return [];
                }

                $widgets = $container['widgets'] ?? [];

                return is_array($widgets) ? $widgets : [];
            })
            ->map(fn (mixed $widget): ?string => is_array($widget)
                ? (string) ($widget['widget_key'] ?? $widget['key'] ?? '')
                : (is_string($widget) ? $widget : null))
            ->filter(fn (?string $key): bool => $key !== null && $key !== '')
            ->values()
            ->all();
    }

    private function hasLayoutBuilderWidgetModel(): bool
    {
        return class_exists(self::LAYOUT_BUILDER_ELEMENT_MODEL);
    }
}
