<?php

declare(strict_types=1);

namespace Capell\DemoKit\LayoutBuilder\Actions;

use Capell\Core\Contracts\Pageable;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\DemoKit\LayoutBuilder\Data\DemoSitePlanData;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\LayoutBuilder\Support\Creator\ContentCreator;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Capell\Navigation\Support\Creator\NavigationDemoCreator;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static bool run(DemoSitePlanData $plan)
 */
class CreateLayoutBuilderDemoSiteAction
{
    use AsFake;
    use AsObject;

    private const string NavigationPackage = 'capell-app/navigation';

    private DemoCreator $demoCreator;

    public function handle(DemoSitePlanData $plan): bool
    {
        $this->demoCreator = new DemoCreator(author: $plan->user);

        $typeCreator = resolve(TypeCreator::class);
        $typeCreator->createDefaultContentType();
        $typeCreator->createBuilderContentType();
        $typeCreator->createBlockTypes();

        /** @var ContentCreator $contentCreator */
        $contentCreator = resolve(ContentCreator::class);

        $this->createSiteContents($contentCreator, $plan->contentTree, $plan->site);

        return $this->createDemoLayouts($plan->site);
    }

    private function createDemoLayouts(Site $site): bool
    {
        $languages = $site->languages;

        $homePage = $site->getHomePage();

        if (! $homePage instanceof Pageable) {
            return false;
        }

        if ($site->default) {
            $this->setupHomepage($homePage);
        }

        $this->setupSiteNavigations($site, $languages, $homePage);

        return true;
    }

    private function setupHomepage(Pageable $page): void
    {
        $layout = $this->getHomeLayout();
        throw_unless($layout instanceof Layout, Exception::class, 'Unable to find homepage layout');

        $page->update(['layout_id' => $layout->id]);

        $containers = $layout->getAttribute('containers');
        $containers = is_array($containers) ? $containers : [];

        $orderedContainers = [];
        $remainingContainers = array_diff_key($containers, array_flip([
            'ap-blocks',
            'main',
            'faq-main',
            'faq-col',
            'secondary',
            'split-two',
        ]));

        $this->populateAPBlocksContainer($orderedContainers);

        $containers = [
            ...$orderedContainers,
            ...$remainingContainers,
        ];

        $layout->update([
            'containers' => $containers,
            'blocks' => $this->layoutBlockKeys($containers),
        ]);
    }

    private function populateAPBlocksContainer(array &$containers): void
    {
        $heroBlock = $this->demoCreator->createHomepageHeroCommandCenterBlock();
        $proofBlock = $this->demoCreator->createHomepageProofStripBlock();
        $showcaseBlock = $this->demoCreator->createHomepageDemoShowcaseBlock();
        $marketplaceBlock = $this->demoCreator->createHomepageMarketplaceBlock();
        $pipelineBlock = $this->demoCreator->createHomepageTechnicalPipelineBlock();
        $routeSplitBlock = $this->demoCreator->createHomepageRouteSplitBlock();
        $finalCtaBlock = $this->demoCreator->createHomepageFinalCtaBlock();

        $containers['ap-blocks'] = [
            'meta' => [
                'colspan' => 12,
            ],
            'blocks' => [
                ['block_key' => $heroBlock->key],
                ['block_key' => $proofBlock->key],
                ['block_key' => $showcaseBlock->key],
                ['block_key' => $marketplaceBlock->key],
                ['block_key' => $pipelineBlock->key],
                ['block_key' => $routeSplitBlock->key],
                ['block_key' => $finalCtaBlock->key],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $containers
     * @return list<string>
     */
    private function layoutBlockKeys(array $containers): array
    {
        return collect($containers)
            ->flatMap(function (mixed $container): array {
                if (! is_array($container)) {
                    return [];
                }

                $blocks = $container['blocks'] ?? [];

                return is_array($blocks) ? $blocks : [];
            })
            ->map(fn (mixed $block): ?string => is_array($block) ? ($block['block_key'] ?? null) : null)
            ->filter(fn (?string $blockKey): bool => is_string($blockKey) && $blockKey !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function createSiteContents(
        ContentCreator $contentCreator,
        array $contentNode,
        Site $site,
        ?EloquentCollection $languages = null,
        ?Model $parent = null,
    ): void {
        $languages ??= $site->languages;

        $contentData = [
            'name' => $contentNode['name']['en'],
        ];

        if ($parent instanceof Model) {
            $contentData['parent_id'] = $parent->getKey();
        }

        foreach ($languages as $language) {
            $code = $language->getAttribute('code');
            $name = is_string($code) ? $contentNode['name'][$code] : null;

            if ($name === null) {
                continue;
            }

            $contentData['translations'][$code] = [
                'title' => $name,
                'content' => $name,
            ];
        }

        $content = $contentCreator->createContent($contentData, $site, $languages);

        if (! isset($contentNode['children'])) {
            return;
        }

        foreach ($contentNode['children'] as $childNode) {
            $this->createSiteContents($contentCreator, $childNode, $site, $languages, $content);
        }
    }

    private function setupSiteNavigations(Site $site, EloquentCollection $languages, Page $homePage): void
    {
        $navigationDemoCreatorClass = NavigationDemoCreator::class;

        if (! CapellCore::isPackageInstalled(self::NavigationPackage) || ! class_exists($navigationDemoCreatorClass)) {
            return;
        }

        $navigationDemoCreator = resolve($navigationDemoCreatorClass);

        $languages->each(function (Language $language) use ($navigationDemoCreator, $site, $homePage): void {
            $navigationDemoCreator->setupMainNavigation($site, $language, $homePage);
            $navigationDemoCreator->setupFooterNavigation($site, $language);
            $navigationDemoCreator->setupSubFooterNavigation($site, $language);
        });
    }

    private function getHomeLayout(): ?Layout
    {
        $layout = Layout::query()->firstWhere('key', LayoutEnum::Home);

        return $layout instanceof Layout ? $layout : null;
    }
}
