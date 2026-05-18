<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions\Diagnostics;

use Capell\Core\Actions\Diagnostics\VerifyFrontendBuildAssetsAction;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Data\Diagnostics\FrontendBuildAssetVerificationResultData;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\DemoKit\Data\DemoProfileData;
use Capell\LayoutBuilder\Models\Element;
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

    private const string LAYOUT_BUILDER_ELEMENT_MODEL = Element::class;

    private DemoProfileData $profile;

    public function handle(): DemoInstallHealthData
    {
        $this->profile = DemoProfileData::default();

        $checks = collect([
            $this->layoutBuilderElementModelExists(),
            $this->homepageExists(),
            $this->homepageLayoutHasElements(),
            $this->homepageStartsWithHero(),
            $this->homepageUsesShowcaseOrder(),
            $this->minimumElementCount(),
            ...($this->hasLayoutBuilderElementModel() ? [
                $this->apElementsHaveAssets(),
                $this->placeholderLabelsAreAbsent(),
            ] : []),
            $this->minimumMediaCount(),
            $this->runtimeAssetsExist(),
        ]);

        return new DemoInstallHealthData($checks);
    }

    private function layoutBuilderElementModelExists(): DoctorCheckResultData
    {
        if ($this->hasLayoutBuilderElementModel()) {
            return new DoctorCheckResultData(
                label: 'Layout Builder demo dependency',
                passed: true,
                message: 'Layout Builder element model is available.',
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

    private function homepageLayoutHasElements(): DoctorCheckResultData
    {
        $layout = $this->homepageLayout();
        $elements = $this->layoutElementKeys($layout);

        if ($elements === []) {
            return new DoctorCheckResultData(
                label: 'Homepage layout has elements',
                passed: false,
                message: 'The homepage layout does not contain any element keys.',
                remediation: 'Run the selected theme setup/demo command after package setup has completed.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Homepage layout has elements',
            passed: true,
            message: sprintf('Homepage layout references %d element occurrence(s).', count($elements)),
        );
    }

    private function minimumElementCount(): DoctorCheckResultData
    {
        $count = $this->homepageElementCount();

        if ($count < $this->profile->minimumElementCount) {
            return new DoctorCheckResultData(
                label: 'Default demo element count',
                passed: false,
                message: sprintf('Homepage has %d element(s); expected at least %d.', $count, $this->profile->minimumElementCount),
                remediation: 'Rerun the demo package step and confirm the demo package runs after setup packages.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo element count',
            passed: true,
            message: sprintf('Homepage has %d element(s).', $count),
        );
    }

    private function homepageUsesShowcaseOrder(): DoctorCheckResultData
    {
        $layout = $this->homepageLayout();
        $elements = $this->layoutElementKeys($layout);
        $actual = array_slice($elements, 0, count($this->profile->showcaseElementOrder));

        if ($actual !== $this->profile->showcaseElementOrder) {
            return new DoctorCheckResultData(
                label: 'Default demo showcase element order',
                passed: false,
                message: sprintf(
                    'Homepage starts with [%s]; expected [%s].',
                    implode(', ', $actual),
                    implode(', ', $this->profile->showcaseElementOrder),
                ),
                remediation: 'Rerun the demo package step so the curated Foundation showcase homepage layout is rebuilt.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo showcase element order',
            passed: true,
            message: 'Homepage uses the curated Foundation showcase element order.',
        );
    }

    private function apElementsHaveAssets(): DoctorCheckResultData
    {
        $elementModel = self::LAYOUT_BUILDER_ELEMENT_MODEL;

        foreach ($this->profile->elementAssetMinimums as $elementKey => $minimum) {
            $element = $elementModel::query()
                ->where('key', $elementKey)
                ->withCount('assets')
                ->first();

            $assetCount = $element instanceof $elementModel ? (int) $element->getAttribute('assets_count') : 0;

            if ($assetCount < $minimum) {
                return new DoctorCheckResultData(
                    label: 'Default demo AP element assets',
                    passed: false,
                    message: sprintf('Element "%s" has %d asset(s); expected at least %d.', $elementKey, $assetCount, $minimum),
                    remediation: 'Rerun the default demo fixtures so AP elements receive their editable content and media assets.',
                );
            }
        }

        return new DoctorCheckResultData(
            label: 'Default demo AP element assets',
            passed: true,
            message: 'AP showcase elements have the expected editable assets.',
        );
    }

    private function homepageStartsWithHero(): DoctorCheckResultData
    {
        $firstElementKey = $this->firstHomepageElementKey();

        if ($firstElementKey === null || ! str_contains($firstElementKey, 'hero')) {
            return new DoctorCheckResultData(
                label: 'Homepage starts with a hero element',
                passed: false,
                message: $firstElementKey === null
                    ? 'The homepage layout has no first element.'
                    : sprintf('The homepage starts with "%s", not a hero element.', $firstElementKey),
                remediation: 'Rerun the demo package step after the selected theme setup so the homepage layout order is rebuilt.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Homepage starts with a hero element',
            passed: true,
            message: sprintf('Homepage starts with "%s".', $firstElementKey),
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
        $elementModel = self::LAYOUT_BUILDER_ELEMENT_MODEL;

        $homepageElementIds = $elementModel::query()
            ->whereIn('key', $this->layoutElementKeys($this->homepageLayout()))
            ->pluck('id');

        $found = Translation::query()
            ->where('translatable_type', resolve($elementModel)->getMorphClass())
            ->whereIn('translatable_id', $homepageElementIds)
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

    private function homepageElementCount(): int
    {
        $layout = $this->homepageLayout();
        if (! $layout instanceof Layout) {
            return 0;
        }

        return count(array_unique($this->layoutElementKeys($layout)));
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

    private function firstHomepageElementKey(): ?string
    {
        $layout = $this->homepageLayout();
        if (! $layout instanceof Layout) {
            return null;
        }

        foreach ($layout->containers ?? [] as $container) {
            if (! is_array($container)) {
                continue;
            }

            $element = collect($container['elements'] ?? [])->first();

            if (! is_array($element)) {
                continue;
            }

            $key = (string) ($element['element_key'] ?? $element['key'] ?? '');

            return $key !== '' ? $key : null;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function layoutElementKeys(?Layout $layout): array
    {
        if (! $layout instanceof Layout) {
            return [];
        }

        return collect($layout->containers ?? [])
            ->flatMap(function (mixed $container): array {
                if (! is_array($container)) {
                    return [];
                }

                $elements = $container['elements'] ?? [];

                return is_array($elements) ? $elements : [];
            })
            ->map(fn (mixed $element): ?string => is_array($element)
                ? (string) ($element['element_key'] ?? $element['key'] ?? '')
                : (is_string($element) ? $element : null))
            ->filter(fn (?string $key): bool => $key !== null && $key !== '')
            ->values()
            ->all();
    }

    private function hasLayoutBuilderElementModel(): bool
    {
        return class_exists(self::LAYOUT_BUILDER_ELEMENT_MODEL);
    }
}
