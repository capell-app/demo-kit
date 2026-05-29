<?php

declare(strict_types=1);

namespace Capell\DemoKit\LayoutBuilder\Actions;

use Capell\Core\Contracts\Pageable;
use Capell\Core\Enums\ContainerWidthEnum;
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
            'hero',
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
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $containers
     */
    private function populateAPBlocksContainer(array &$containers): void
    {
        $heroBlock = $this->demoCreator->createHomepageHeroCommandCenterBlock();
        $proofBlock = $this->demoCreator->createHomepageProofStripBlock();
        $showcaseBlock = $this->demoCreator->createHomepageDemoShowcaseBlock();
        $widgetsCarouselBlock = $this->demoCreator->createHomepageDemoWidgetsCarouselBlock();
        $marketplaceBlock = $this->demoCreator->createHomepageMarketplaceBlock();
        $pipelineBlock = $this->demoCreator->createHomepageTechnicalPipelineBlock();
        $routeSplitBlock = $this->demoCreator->createHomepageRouteSplitBlock();
        $finalCtaBlock = $this->demoCreator->createHomepageFinalCtaBlock();

        $containers['hero'] = [
            'meta' => [
                'colspan' => 12,
                'container' => ContainerWidthEnum::Full,
            ],
            'widgets' => [
                ['widget_key' => $heroBlock->key],
            ],
        ];

        $containers['ap-blocks'] = [
            'meta' => [
                'colspan' => 12,
            ],
            'widgets' => [
                ['widget_key' => $proofBlock->key],
                ['widget_key' => $showcaseBlock->key],
                ['widget_key' => $widgetsCarouselBlock->key],
                ['widget_key' => $marketplaceBlock->key],
                ['widget_key' => $pipelineBlock->key],
                ['widget_key' => $routeSplitBlock->key],
            ],
        ];

        $containers['final-cta'] = [
            'meta' => [
                'colspan' => 12,
                'container' => ContainerWidthEnum::Full,
                'html_class' => 'bg-slate-950',
            ],
            'widgets' => [
                ['widget_key' => $finalCtaBlock->key],
            ],
        ];
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     * @param  array<array-key, mixed>  $contentNode
     */
    private function createSiteContents(
        ContentCreator $contentCreator,
        array $contentNode,
        Site $site,
        ?EloquentCollection $languages = null,
        ?Model $parent = null,
    ): void {
        $languages ??= $site->languages;
        $contentNames = is_array($contentNode['name'] ?? null) ? $contentNode['name'] : [];

        $contentData = [
            'name' => $this->preferredTranslatedValue($contentNames, $languages),
        ];

        if ($parent instanceof Model) {
            $contentData['parent_id'] = $parent->getKey();
        }

        foreach ($languages as $language) {
            $code = $language->getAttribute('code');
            $name = is_string($code) ? ($contentNames[$code] ?? null) : null;

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

    /**
     * @param  array<array-key, mixed>  $values
     * @param  EloquentCollection<int, Language>  $languages
     */
    private function preferredTranslatedValue(array $values, EloquentCollection $languages): string
    {
        foreach ($languages as $language) {
            $value = $values[$language->code] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $englishValue = $values['en'] ?? null;

        if (is_string($englishValue) && $englishValue !== '') {
            return $englishValue;
        }

        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        throw new Exception('Demo content data must include at least one translated name.');
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     */
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
