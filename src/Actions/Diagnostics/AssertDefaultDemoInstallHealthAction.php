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
use Capell\LayoutBuilder\Models\Block;
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

    private const string LAYOUT_BUILDER_ELEMENT_MODEL = Block::class;

    private DemoProfileData $profile;

    public function handle(): DemoInstallHealthData
    {
        $this->profile = DemoProfileData::default();

        $checks = collect([
            $this->layoutBuilderBlockModelExists(),
            $this->homepageExists(),
            $this->homepageLayoutHasBlocks(),
            $this->homepageStartsWithHero(),
            $this->homepageUsesShowcaseOrder(),
            $this->minimumBlockCount(),
            ...($this->hasLayoutBuilderBlockModel() ? [
                $this->apBlocksHaveAssets(),
                $this->placeholderLabelsAreAbsent(),
            ] : []),
            $this->minimumMediaCount(),
            $this->runtimeAssetsExist(),
        ]);

        return new DemoInstallHealthData($checks);
    }

    private function layoutBuilderBlockModelExists(): DoctorCheckResultData
    {
        if ($this->hasLayoutBuilderBlockModel()) {
            return new DoctorCheckResultData(
                label: 'Layout Builder demo dependency',
                passed: true,
                message: 'Layout Builder block model is available.',
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

    private function homepageLayoutHasBlocks(): DoctorCheckResultData
    {
        $layout = $this->homepageLayout();
        $blocks = $this->layoutBlockKeys($layout);

        if ($blocks === []) {
            return new DoctorCheckResultData(
                label: 'Homepage layout has blocks',
                passed: false,
                message: 'The homepage layout does not contain any block keys.',
                remediation: 'Run the selected theme setup/demo command after package setup has completed.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Homepage layout has blocks',
            passed: true,
            message: sprintf('Homepage layout references %d block occurrence(s).', count($blocks)),
        );
    }

    private function minimumBlockCount(): DoctorCheckResultData
    {
        $count = $this->homepageBlockCount();

        if ($count < $this->profile->minimumBlockCount) {
            return new DoctorCheckResultData(
                label: 'Default demo block count',
                passed: false,
                message: sprintf('Homepage has %d block(s); expected at least %d.', $count, $this->profile->minimumBlockCount),
                remediation: 'Rerun the demo package step and confirm the demo package runs after setup packages.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo block count',
            passed: true,
            message: sprintf('Homepage has %d block(s).', $count),
        );
    }

    private function homepageUsesShowcaseOrder(): DoctorCheckResultData
    {
        $layout = $this->homepageLayout();
        $blocks = $this->layoutBlockKeys($layout);
        $actual = array_slice($blocks, 0, count($this->profile->showcaseBlockOrder));

        if ($actual !== $this->profile->showcaseBlockOrder) {
            return new DoctorCheckResultData(
                label: 'Default demo showcase block order',
                passed: false,
                message: sprintf(
                    'Homepage starts with [%s]; expected [%s].',
                    implode(', ', $actual),
                    implode(', ', $this->profile->showcaseBlockOrder),
                ),
                remediation: 'Rerun the demo package step so the curated Foundation showcase homepage layout is rebuilt.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Default demo showcase block order',
            passed: true,
            message: 'Homepage uses the curated Foundation showcase block order.',
        );
    }

    private function apBlocksHaveAssets(): DoctorCheckResultData
    {
        $blockModel = self::LAYOUT_BUILDER_ELEMENT_MODEL;

        foreach ($this->profile->blockAssetMinimums as $blockKey => $minimum) {
            $block = $blockModel::query()
                ->where('key', $blockKey)
                ->withCount('assets')
                ->first();

            $assetCount = $block instanceof $blockModel ? (int) $block->getAttribute('assets_count') : 0;

            if ($assetCount < $minimum) {
                return new DoctorCheckResultData(
                    label: 'Default demo AP block assets',
                    passed: false,
                    message: sprintf('Block "%s" has %d asset(s); expected at least %d.', $blockKey, $assetCount, $minimum),
                    remediation: 'Rerun the default demo fixtures so AP blocks receive their editable content and media assets.',
                );
            }
        }

        return new DoctorCheckResultData(
            label: 'Default demo AP block assets',
            passed: true,
            message: 'AP showcase blocks have the expected editable assets.',
        );
    }

    private function homepageStartsWithHero(): DoctorCheckResultData
    {
        $firstBlockKey = $this->firstHomepageBlockKey();

        if ($firstBlockKey === null || ! str_contains($firstBlockKey, 'hero')) {
            return new DoctorCheckResultData(
                label: 'Homepage starts with a hero block',
                passed: false,
                message: $firstBlockKey === null
                    ? 'The homepage layout has no first block.'
                    : sprintf('The homepage starts with "%s", not a hero block.', $firstBlockKey),
                remediation: 'Rerun the demo package step after the selected theme setup so the homepage layout order is rebuilt.',
            );
        }

        return new DoctorCheckResultData(
            label: 'Homepage starts with a hero block',
            passed: true,
            message: sprintf('Homepage starts with "%s".', $firstBlockKey),
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
        $blockModel = self::LAYOUT_BUILDER_ELEMENT_MODEL;

        $homepageBlockIds = $blockModel::query()
            ->whereIn('key', $this->layoutBlockKeys($this->homepageLayout()))
            ->pluck('id');

        $found = Translation::query()
            ->where('translatable_type', resolve($blockModel)->getMorphClass())
            ->whereIn('translatable_id', $homepageBlockIds)
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

    private function homepageBlockCount(): int
    {
        $layout = $this->homepageLayout();
        if (! $layout instanceof Layout) {
            return 0;
        }

        return count(array_unique($this->layoutBlockKeys($layout)));
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

    private function firstHomepageBlockKey(): ?string
    {
        $layout = $this->homepageLayout();
        if (! $layout instanceof Layout) {
            return null;
        }

        foreach ($layout->containers ?? [] as $container) {
            if (! is_array($container)) {
                continue;
            }

            $block = collect($container['blocks'] ?? [])->first();

            if (! is_array($block)) {
                continue;
            }

            $key = (string) ($block['block_key'] ?? $block['key'] ?? '');

            return $key !== '' ? $key : null;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function layoutBlockKeys(?Layout $layout): array
    {
        if (! $layout instanceof Layout) {
            return [];
        }

        return collect($layout->containers ?? [])
            ->flatMap(function (mixed $container): array {
                if (! is_array($container)) {
                    return [];
                }

                $blocks = $container['blocks'] ?? [];

                return is_array($blocks) ? $blocks : [];
            })
            ->map(fn (mixed $block): ?string => is_array($block)
                ? (string) ($block['block_key'] ?? $block['key'] ?? '')
                : (is_string($block) ? $block : null))
            ->filter(fn (?string $key): bool => $key !== null && $key !== '')
            ->values()
            ->all();
    }

    private function hasLayoutBuilderBlockModel(): bool
    {
        return class_exists(self::LAYOUT_BUILDER_ELEMENT_MODEL);
    }
}
