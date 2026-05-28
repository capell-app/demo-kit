<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use BackedEnum;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Media;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\LayoutBuilder\Actions\CreateHeroBlockAction;
use Capell\LayoutBuilder\Enums\BlockComponentEnum;
use Capell\LayoutBuilder\Enums\BlockTypeEnum;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Capell\Navigation\Support\Creator\NavigationCreator;
use Error;
use Exception;
use FilesystemIterator;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;
use SplFileInfo;
use ZipArchive;

abstract class BaseDemoCreator
{
    protected const string NavigationPackage = 'capell-app/navigation';

    protected const string FormBuilderPackage = 'capell-app/form-builder';

    protected const array StandardFooterPageNames = [
        'Integrations',
        'Locations',
        'Partners',
        'Roadmap',
        'Governance',
        'Training',
    ];

    /** @var class-string<Language> */
    public string $languageModel;

    /** @var class-string<Site> */
    public string $siteModel;

    /** @var class-string<Page> */
    public string $pageModel;

    /** @var class-string<Translation> */
    public string $translationModel;

    /** @var class-string<Layout> */
    public string $layoutModel;

    /** @var class-string<Blueprint> */
    public string $typeModel;

    /**
     * @var array<string, list<string>>
     */
    protected static array $demoImageFilenames = [];

    /** @var class-string<Model&HasMedia> */
    protected string $contentModel;

    /** @var class-string<Widget> */
    protected string $blockModel;

    protected ?Widget $demoPageContentBlock = null;

    public static function getDemoResourcePath(?string $folder): string
    {
        return resolve(DemoResourceResolver::class)->resolve($folder);
    }

    public function getRandomDemoImage(string $path, string $extension = 'jpg'): string
    {
        $ext = strtolower($extension);
        $cacheKey = $path . '|' . $ext;

        if (! array_key_exists($cacheKey, self::$demoImageFilenames)) {
            self::$demoImageFilenames[$cacheKey] = [];

            $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $fileInfo) {
                if (! $fileInfo instanceof SplFileInfo) {
                    continue;
                }

                if (! $fileInfo->isFile()) {
                    continue;
                }

                $fileExtension = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
                if ($fileExtension !== $ext) {
                    continue;
                }

                self::$demoImageFilenames[$cacheKey][] = pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME);
            }
        }

        $filenames = self::$demoImageFilenames[$cacheKey];
        throw_if($filenames === [], Exception::class, 'No demo files with extension .' . $extension . ' found in the specified path: ' . $path);

        return $filenames[mt_rand(0, count($filenames) - 1)];
    }

    /**
     * @throws FileIsTooBig
     * @throws FileDoesNotExist
     * @throws Exception
     */
    public function createMedia(Model $model, ?string $name = null, string $type = 'image', BackedEnum|string $collection = MediaCollectionEnum::Image): void
    {
        if (! $model instanceof HasMedia) {
            return;
        }

        if (! $model->exists || $this->hasExistingMedia($model, $collection)) {
            return;
        }

        if ((method_exists($model, 'trashed') && $model->trashed()) || ! $model->newQuery()->whereKey($model->getKey())->exists()) {
            return;
        }

        if ($type === 'video') {
            $ext = 'mp4';
            $demo_path = static::getDemoResourcePath('video');
            $filename = $name ?? 'SampleVideo_1280x720_1mb';
            $collection = MediaCollectionEnum::Video;
        } else {
            $ext = 'jpg';
            $demo_path = static::getDemoResourcePath('img');
            $filename = in_array($name, [null, '', '0'], true) ? null : Str::slug($name);
        }

        if ($filename !== null) {
            $filename = pathinfo($filename, PATHINFO_FILENAME);
        }

        $demo_file = sprintf('%s/%s.%s', $demo_path, $filename, $ext);

        if (in_array($filename, ['', '0', [], null], true) || ! File::exists($demo_file)) {
            $demo_path = static::getDemoResourcePath('img');
            $ext = 'jpg';
            $filename = $this->getRandomDemoImage($demo_path, $ext);
            $demo_file = sprintf('%s/%s.%s', $demo_path, $filename, $ext);
        }

        $customProps = $type === 'video' ? [] : $this->imageDimensions($demo_file);

        if (! File::exists($demo_file)) {
            return;
        }

        gc_collect_cycles();

        try {
            $model->addMedia($demo_file)
                ->preservingOriginal()
                ->withCustomProperties($customProps)
                ->toMediaCollection($this->mediaCollectionName($collection));

            gc_collect_cycles();
        } catch (ModelNotFoundException $exception) {
            if ($exception->getModel() === Media::class) {
                return;
            }

            throw $exception;
        } catch (Error $error) {
            if (str_contains($error->getMessage(), 'Call to a member function getMedia() on null')) {
                return;
            }

            throw $error;
        }
    }

    protected static function ensureStorageDemoResources(): string
    {
        return resolve(DemoResourceResolver::class)->ensureStorageDemoResources();
    }

    protected static function assertSafeDemoZipEntries(ZipArchive $zip): void
    {
        resolve(DemoResourceResolver::class)->assertSafeDemoZipEntries($zip);
    }

    /**
     * @param  Collection<int, Site>  $sites
     */
    protected function attachRelatedSites(Site $defaultSite, Collection $sites): void
    {
        $defaultSite->related()
            ->attach($sites->where('id', '!=', $defaultSite->id))
            ->save();
    }

    /**
     * @return Collection<int, Site>
     */
    protected function findRelatedSites(Site $site): Collection
    {
        $language_ids = $site->translations->pluck('language_id');

        return $this->siteModel::query()
            ->with(['language'])
            ->withWhereHas(
                'translation',
                fn (BuilderContract $query): BuilderContract => $query->whereIn('translations.language_id', $language_ids),
            )
            ->whereNot('sites.id', $site->id)
            ->get();
    }

    /**
     * @param  Collection<int, Page>  $siteTree
     * @return array<array-key, mixed>
     */
    protected function navigationPageItems(Collection $siteTree, Language $language): array
    {
        $items = [];

        foreach ($siteTree as $page) {
            if (! $page instanceof Page) {
                continue;
            }

            $items[(string) Str::uuid()] = [
                'label' => $this->getPageNavigationLabel($page, $language),
                'type' => 'page',
                'data' => [
                    'pageable_id' => $page->id,
                    'pageable_type' => $page->getMorphClass(),
                ],
                'children' => $page->relationLoaded('children') ? $this->navigationPageItems($page->children, $language) : [],
            ];
        }

        return $items;
    }

    protected function getPageNavigationLabel(Page $page, Language $language): string
    {
        $navigationCreator = NavigationCreator::class;

        if (CapellCore::isPackageInstalled(self::NavigationPackage) && class_exists($navigationCreator)) {
            return $navigationCreator::getPageNavigationLabel($page, $language);
        }

        return $page->translation->title ?? $page->name;
    }

    protected function hasExistingMedia(Model&HasMedia $model, BackedEnum|string $collection): bool
    {
        return $model->getMedia($this->mediaCollectionName($collection))->isNotEmpty();
    }

    protected function mediaCollectionName(BackedEnum|string $collection): string
    {
        if ($collection instanceof BackedEnum) {
            return (string) $collection->value;
        }

        return $collection;
    }

    /** @return MorphMany<Translation, Model> */
    protected function translationsFor(Model $model): MorphMany
    {
        return $model->morphMany(Translation::class, 'translatable');
    }

    protected function createPageBlockAsset(Widget $block, Pageable $page, string $container, int $occurrence, Model $asset): WidgetAsset
    {
        $blockAsset = DB::transaction(
            fn (): Model => $block->assets()->createOrFirst([
                'pageable_id' => $page->getKey(),
                'pageable_type' => $page->getMorphClass(),
                'container' => $container,
                'occurrence' => $occurrence,
                'asset_type' => $asset->getMorphClass(),
                'asset_id' => $asset->getKey(),
            ]),
            attempts: 5,
        );

        throw_unless($blockAsset instanceof WidgetAsset, RuntimeException::class, 'Layout block asset creation must return a block asset model.');

        return $blockAsset;
    }

    protected function ensureDemoPageContentBlock(): Widget
    {
        if ($this->demoPageContentBlock instanceof Widget) {
            return $this->demoPageContentBlock;
        }

        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', BlockTypeEnum::PageContents);

        $blockType ??= resolve(TypeCreator::class)->pageContentBlockType();

        $attributes = [
            'name' => 'Demo Page Content',
            'blueprint_id' => $blockType->id,
            'component' => DemoKitServiceProvider::DemoPageContentRenderable,
            'view_file' => null,
            'meta' => [
                'component' => DemoKitServiceProvider::DemoPageContentRenderable,
                'page_content' => ['content'],
            ],
            'status' => true,
        ];

        $block = Widget::query()->firstOrCreate(['key' => 'demo-page-content'], $attributes);
        $block->forceFill($attributes);

        if ($block->isDirty()) {
            $block->save();
        }

        return $this->demoPageContentBlock = $block;
    }

    protected function syncDemoPageContentAssets(Page $page, string $name): void
    {
        if (! CapellCore::hasAsset('Section') || ! Schema::hasTable(resolve($this->contentModel)->getTable())) {
            return;
        }

        $definitions = $this->demoPageAssetDefinitions($name);

        if ($definitions === []) {
            return;
        }

        $block = $this->ensureDemoPageContentBlock();
        $assetType = resolve($this->contentModel)->getMorphClass();
        $activeKeys = array_column($definitions, 'key');

        DB::transaction(function () use ($activeKeys, $assetType, $block, $definitions, $page): void {
            $existingSeededAssets = $block->assets()
                ->where([
                    'pageable_id' => $page->getKey(),
                    'pageable_type' => $page->getMorphClass(),
                    'container' => 'main',
                    'occurrence' => 1,
                ])
                ->get()
                ->filter(fn (WidgetAsset $asset): bool => ($asset->meta['demo_kit_seed'] ?? false) === true);

            $existingSeededAssets
                ->reject(fn (WidgetAsset $asset): bool => in_array($asset->meta['demo_page_asset_key'] ?? null, $activeKeys, true))
                ->each(fn (WidgetAsset $asset): ?bool => $asset->delete());

            foreach ($definitions as $order => $definition) {
                $asset = $this->createDemoPageContentAsset($page, $definition);

                $block->assets()->updateOrCreate(
                    [
                        'pageable_id' => $page->getKey(),
                        'pageable_type' => $page->getMorphClass(),
                        'container' => 'main',
                        'occurrence' => 1,
                        'asset_type' => $assetType,
                        'asset_id' => $asset->getKey(),
                        'workspace_id' => 0,
                    ],
                    [
                        'order' => $order,
                        'meta' => [
                            'demo_kit_seed' => true,
                            'demo_page_asset_key' => $definition['key'],
                            'variant' => $definition['variant'],
                            'layout' => $definition['layout'],
                            'eyebrow' => $definition['eyebrow'],
                            'title' => $definition['title'],
                            'intro' => $definition['intro'],
                            'items' => $definition['items'] ?? [],
                            'metrics' => $definition['metrics'] ?? [],
                            'steps' => $definition['steps'] ?? [],
                            'filters' => $definition['filters'] ?? [],
                            'cta' => $definition['cta'] ?? [],
                        ],
                    ],
                );
            }
        }, attempts: 5);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function createDemoPageContentAsset(Page $page, array $definition): Model
    {
        $sectionType = Blueprint::query()->firstOrCreate(
            [
                'type' => 'section',
                'key' => 'demo-page-content-asset',
            ],
            [
                'name' => 'Demo Page Content Asset',
                'group' => 'demo',
                'status' => true,
            ],
        );

        $asset = $this->contentModel::query()->updateOrCreate(
            [
                'name' => sprintf('Demo page asset: %s: %s', $page->name, $definition['key']),
                'blueprint_id' => $sectionType->getKey(),
            ],
            [
                'site_id' => $page->site_id,
                'order' => (int) ($definition['order'] ?? 0),
                'meta' => [
                    'demo_kit_seed' => true,
                    'demo_page_asset_key' => $definition['key'],
                    'variant' => $definition['variant'],
                ],
                'visible_from' => now()->subDay(),
            ],
        );

        throw_unless($asset instanceof Model, RuntimeException::class, 'Demo page content asset creation must return a model.');

        return $asset;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function demoPageAssetDefinitions(string $name): array
    {
        $name = $this->canonicalDemoPageName($name);

        $definitions = [
            'Services' => [
                'variant' => 'services-workbench',
                'layout' => 'services-workbench',
                'eyebrow' => 'Implementation studio',
                'title' => 'Services for Capell sites that cannot afford template drift',
                'intro' => 'A sharper delivery workbench for content modelling, migration paths, layout architecture, package boundaries, launch verification, and editor handover.',
                'items' => [
                    ['label' => 'Audit lane', 'title' => 'Content model review', 'copy' => 'Map pages, assets, routes, redirects, and ownership before implementation starts.'],
                    ['label' => 'Build lane', 'title' => 'Layout architecture', 'copy' => 'Create reusable blocks editors can compose without breaking public output.'],
                    ['label' => 'Launch lane', 'title' => 'Release checks', 'copy' => 'Verify cache, navigation, search, SEO, and anonymous page safety before handover.'],
                ],
                'metrics' => [
                    ['value' => '6 wk', 'label' => 'typical build sprint'],
                    ['value' => '12+', 'label' => 'page shapes mapped'],
                    ['value' => '0', 'label' => 'admin metadata leaks'],
                    ['value' => '4', 'label' => 'handover checkpoints'],
                ],
                'steps' => [
                    ['label' => '01', 'title' => 'Audit the content model', 'copy' => 'Inventory pages, media, routes, redirects, permissions, integrations, and editorial risks.'],
                    ['label' => '02', 'title' => 'Shape reusable layouts', 'copy' => 'Turn page intent into governed sections instead of another stack of bespoke templates.'],
                    ['label' => '03', 'title' => 'Build package-owned surfaces', 'copy' => 'Keep Blade, render data, cache, and tests close to the package that owns the behaviour.'],
                    ['label' => '04', 'title' => 'Verify public output', 'copy' => 'Check anonymous rendering, navigation, search, SEO, and visual regressions before handover.'],
                ],
            ],
            'Pricing' => [
                'variant' => 'pricing-matrix',
                'layout' => 'pricing-matrix',
                'eyebrow' => 'Commercial model',
                'title' => 'Pricing built around risk, support, and delivery clarity',
                'intro' => 'An editorial pricing surface with plan cards, implementation guardrails, migration confidence, and support paths separated clearly.',
                'items' => [
                    ['label' => 'Developer', 'title' => 'GBP 0', 'copy' => 'For evaluation, prototypes, and local proof-of-concept work.'],
                    ['label' => 'Agency', 'title' => 'GBP 99', 'copy' => 'For production delivery with commercial support and implementation confidence.'],
                    ['label' => 'Enterprise', 'title' => 'Custom', 'copy' => 'For governed estates, multi-site publishing, and dedicated support paths.'],
                ],
                'steps' => [
                    ['label' => 'Support', 'title' => 'Response model', 'copy' => 'Pick a support level separately from implementation scope.'],
                    ['label' => 'Migration', 'title' => 'Import confidence', 'copy' => 'Add migration help when source data and redirects need proof.'],
                    ['label' => 'Delivery', 'title' => 'Scoped change', 'copy' => 'Commercial changes are priced before implementation work starts.'],
                ],
            ],
            'Resources' => [
                'variant' => 'resources-library',
                'layout' => 'resources-library',
                'eyebrow' => 'Resource library',
                'title' => 'Resource library for Capell builders',
                'intro' => 'A dense editorial library with featured guidance, category filters, implementation references, and a toolkit CTA.',
                'filters' => ['All resources', 'Architecture', 'Migration', 'Publishing', 'Theme systems'],
                'items' => [
                    ['label' => 'Featured guide', 'title' => 'Scaling Laravel CMS architecture for 1M+ records', 'copy' => 'A dense implementation note on content modelling, search, cache invalidation, and public rendering at scale.'],
                    ['label' => 'Migration', 'title' => 'Designing imports editors can trust', 'copy' => 'Validate source rows, preserve redirects, and keep rejected records explainable.'],
                    ['label' => 'Publishing', 'title' => 'Approval workflows without admin leakage', 'copy' => 'Keep draft tooling private while public pages stay clean and cacheable.'],
                    ['label' => 'Theme systems', 'title' => 'Package-owned frontend rendering', 'copy' => 'Build reusable public surfaces without coupling them to Filament screens.'],
                    ['label' => 'Architecture', 'title' => 'Layout containers that stack predictably', 'copy' => 'Use explicit regions, ordering, and responsive constraints so sidebars become clean mobile sections.'],
                    ['label' => 'Publishing', 'title' => 'Preview discipline for content teams', 'copy' => 'Give editors confidence without letting preview URLs or authoring state leak into public output.'],
                    ['label' => 'Migration', 'title' => 'Redirect evidence for legacy estates', 'copy' => 'Pair imported content with route checks, rejected row reports, and cache-safe publishing decisions.'],
                ],
                'cta' => ['label' => 'Plan a rollout', 'href' => '/contact'],
            ],
            'Team' => [
                'variant' => 'team-capability',
                'layout' => 'proof-board',
                'eyebrow' => 'Delivery team',
                'title' => 'Implementation specialists for Capell websites',
                'intro' => 'A capability-led profile board that maps roles to the work needed for flexible Capell sites.',
                'items' => [
                    ['label' => 'Strategy', 'title' => 'CMS architecture', 'copy' => 'Owns page models, routes, package boundaries, and release shape.'],
                    ['label' => 'Frontend', 'title' => 'Public rendering', 'copy' => 'Builds Tailwind and Blade surfaces that stay clean for visitors.'],
                    ['label' => 'Publishing', 'title' => 'Workflow setup', 'copy' => 'Connects Filament editing, preview, approval, and handover.'],
                ],
            ],
            'Testimonials' => [
                'variant' => 'testimonial-proof',
                'layout' => 'quote-board',
                'eyebrow' => 'Customer proof',
                'title' => 'What Capell builders say',
                'intro' => 'Outcome proof grouped by the people who need the CMS to work every day.',
                'items' => [
                    ['label' => 'Agency', 'title' => 'Faster rebuilds', 'copy' => 'Reusable blocks reduced one-off template work across the site.'],
                    ['label' => 'Editor', 'title' => 'Clear ownership', 'copy' => 'Teams can update copy and media without touching implementation details.'],
                    ['label' => 'Engineering', 'title' => 'Cleaner releases', 'copy' => 'Public output remains cacheable and separate from admin tooling.'],
                ],
            ],
            'Projects' => [
                'variant' => 'project-index',
                'layout' => 'project-index',
                'eyebrow' => 'Project library',
                'title' => 'Capell implementation project library',
                'intro' => 'Structured project cards that pair scope, outcome, and delivery evidence.',
                'items' => [
                    ['label' => 'Case study', 'title' => 'Layout builder redesign', 'copy' => 'A flexible page system rebuilt around reusable sections and assets.'],
                    ['label' => 'Migration', 'title' => 'Resource library import', 'copy' => 'Structured content and redirects moved into a governed CMS workflow.'],
                    ['label' => 'Launch', 'title' => 'Static delivery rollout', 'copy' => 'Cache generation and public verification before handover.'],
                ],
            ],
            'Project Detail' => [
                'variant' => 'project-detail',
                'layout' => 'case-study',
                'eyebrow' => 'Project detail',
                'title' => 'Layout builder redesign for a flexible Capell website',
                'intro' => 'A case-study narrative that explains brief, scope, solution, and outcome without hard-coding that story into CMS prose.',
                'steps' => [
                    ['label' => 'Brief', 'title' => 'Reusable page sections', 'copy' => 'Keep existing content intent while improving layout ownership.'],
                    ['label' => 'Solution', 'title' => 'Package-owned rendering', 'copy' => 'Editors compose sections while developers keep the public surface in Blade.'],
                    ['label' => 'Outcome', 'title' => 'Cleaner publishing', 'copy' => 'Safer composition, clearer QA, and a documented release path.'],
                ],
            ],
            'Platform Architecture' => [
                'variant' => 'platform-architecture',
                'layout' => 'architecture-layers',
                'eyebrow' => 'Architecture',
                'title' => 'Platform architecture for maintainable CMS delivery',
                'intro' => 'A layered technical page that separates content records, layouts, render data, public components, and package extension points.',
                'items' => [
                    ['label' => 'Core', 'title' => 'Content records', 'copy' => 'Pages, translations, media, layouts, and URLs stay structured.'],
                    ['label' => 'Theme', 'title' => 'Public rendering', 'copy' => 'Blade components own the frontend surface and cacheable output.'],
                    ['label' => 'Package', 'title' => 'Extension points', 'copy' => 'Packages add behaviour without leaking admin concerns to visitors.'],
                ],
            ],
            'FAQ' => [
                'variant' => 'faq-support',
                'layout' => 'faq-support',
                'eyebrow' => 'Support layout',
                'title' => 'Frequently Asked Questions without a hero dependency',
                'intro' => 'A calm support page with native disclosure sections and clear next-step guidance.',
                'items' => [
                    ['label' => 'Question', 'title' => 'Can a page skip the hero entirely?', 'copy' => 'Yes. Pages can render directly into support, article, pricing, or project layouts.'],
                    ['label' => 'Question', 'title' => 'Where does the designed markup live?', 'copy' => 'The demo page-content block owns the Blade presentation. The database stores portable content only.'],
                    ['label' => 'Question', 'title' => 'Can editors still update the copy?', 'copy' => 'Yes. Saved page content renders before the template-specific proof modules.'],
                ],
            ],
            'Contact' => [
                'variant' => 'contact-routing',
                'layout' => 'contact-routing',
                'eyebrow' => 'Contact',
                'title' => 'Start the right conversation',
                'intro' => 'Tell us what you are planning, fixing, moving, or partnering on. One governed contact page routes project scoping, technical support, migrations, and partnerships to the right Capell team.',
                'items' => [
                    ['label' => 'Project scoping', 'title' => 'New implementations', 'copy' => 'Plan content models, package boundaries, layouts, and launch checks before the build starts.'],
                    ['label' => 'Support', 'title' => 'Existing site help', 'copy' => 'Route production issues, editor workflow questions, and package troubleshooting to the right owner.'],
                    ['label' => 'Migration planning', 'title' => 'Move from legacy CMSs', 'copy' => 'Map pages, redirects, media, structured fields, and verification work into a clear migration path.'],
                    ['label' => 'Partnerships', 'title' => 'Agency and technology work', 'copy' => 'Discuss delivery partnerships, packaged integrations, and repeatable theme or content operations.'],
                ],
                'cta' => ['label' => 'Use the contact form', 'href' => '#contact-form-contact-form-0'],
            ],
        ];

        if (in_array($name, self::StandardFooterPageNames, true)) {
            return [[
                'key' => Str::slug($name) . '-footer-route',
                'variant' => 'compact-footer-route',
                'layout' => 'compact-route',
                'eyebrow' => $name,
                'title' => sprintf('%s content with local proof and reusable route structure', $name),
                'intro' => 'A compact footer-page pattern with portable editorial copy, local proof cards, and consistent navigation structure.',
                'items' => [
                    ['label' => 'Route signal', 'title' => 'Mapped', 'copy' => 'Content, routes, and ownership are visible.'],
                    ['label' => 'Public proof', 'title' => 'Verified', 'copy' => 'Output can be checked before handover.'],
                ],
            ]];
        }

        if (in_array($name, ['Compliance', 'Sustainability'], true)) {
            return [[
                'key' => Str::slug($name) . '-location-detail',
                'variant' => Str::slug($name) . '-location-detail',
                'layout' => 'compact-route',
                'eyebrow' => 'Location detail',
                'title' => $name === 'Compliance'
                    ? 'Compliance content for regional obligations'
                    : 'Sustainability content for local initiatives',
                'intro' => $name === 'Compliance'
                    ? 'Local teams can explain regional obligations, review cadence, policy ownership, and evidence without changing the shared location model.'
                    : 'Local initiatives, measurements, and proof points stay consistent across the network while remaining editable by regional owners.',
                'items' => [
                    ['label' => 'Owner', 'title' => 'Local editor', 'copy' => 'Regional teams maintain evidence without changing the shared rendering system.'],
                    ['label' => 'Cadence', 'title' => 'Reviewed quarterly', 'copy' => 'Governed proof stays close to the local publishing workflow.'],
                ],
            ]];
        }

        if (! array_key_exists($name, $definitions)) {
            return [];
        }

        $definition = $definitions[$name];

        return [[
            'key' => Str::slug($name) . '-' . $definition['variant'],
            ...$definition,
        ]];
    }

    protected function layoutForDemoPage(string $name): ?Layout
    {
        $name = $this->canonicalDemoPageName($name);
        $demoPageContentBlock = $this->ensureDemoPageContentBlock();
        $pageBottomBannerBlock = $this->ensurePageBottomBannerBlock();

        $templateLayouts = [
            'About Us' => ['capell-demo-about', 'Capell Demo About', true],
            'Homepage 2' => ['capell-demo-homepage-2', 'Capell Demo Homepage 2', false],
            'Services' => ['capell-demo-services', 'Capell Demo Services', true],
            'Team' => ['capell-demo-team', 'Capell Demo Team', true],
            'FAQ' => ['capell-demo-faq-no-hero', 'Capell Demo FAQ Without Hero', true],
            'Pricing' => ['capell-demo-pricing', 'Capell Demo Pricing', true],
            'Implementation' => ['capell-demo-implementation-pricing', 'Capell Demo Implementation Pricing', true],
            'Testimonials' => ['capell-demo-testimonials', 'Capell Demo Testimonials', true],
            'Projects' => ['capell-demo-projects', 'Capell Demo Projects', true],
            'Project Detail' => ['capell-demo-project-detail', 'Capell Demo Project Detail', true],
            'Blog' => ['capell-demo-blog', 'Capell Demo Blog', true],
            'Home, Buildings and Architecture' => ['capell-demo-single-post', 'Capell Demo Single Post', true],
            'Resources' => ['capell-demo-resources', 'Capell Demo Resources', true],
            'Platform Architecture' => ['capell-demo-platform-architecture', 'Capell Demo Platform Architecture', true],
            'Compliance' => ['capell-demo-compliance-location', 'Capell Demo Compliance Location', true],
            'Sustainability' => ['capell-demo-sustainability-location', 'Capell Demo Sustainability Location', true],
        ];

        if (array_key_exists($name, $templateLayouts)) {
            [$key, $layoutName, $withBreadcrumbs] = $templateLayouts[$name];

            return $this->demoPageLayout(
                $key,
                $layoutName,
                $withBreadcrumbs,
                ! in_array($name, ['FAQ', 'Project Detail'], true),
            );
        }

        if (in_array($name, self::StandardFooterPageNames, true)) {
            return $this->layoutModel::query()->firstOrCreate(
                ['key' => 'footer-standard'],
                [
                    'name' => 'Footer Standard',
                    'group' => 'default',
                    'containers' => [
                        'main' => [
                            'meta' => [
                                'colspan' => 12,
                            ],
                            'widgets' => [
                                ['widget_key' => 'breadcrumbs'],
                                ['widget_key' => $demoPageContentBlock->key],
                            ],
                        ],
                    ],
                    'meta' => [
                        'description' => 'A full-width editorial layout for shared footer pages.',
                    ],
                    'default' => false,
                    'status' => true,
                ],
            );
        }

        if ($name !== 'Contact') {
            return null;
        }

        $attributes = [
            'name' => 'Contact Standalone',
            'group' => 'default',
            'containers' => [
                'main' => [
                    'meta' => [
                        'colspan' => 12,
                    ],
                    'widgets' => [
                        ['widget_key' => 'breadcrumbs'],
                    ],
                ],
                'contact-copy' => [
                    'meta' => [
                        'colspan' => 12,
                        'spacing' => 'lg',
                        'html_class' => 'capell-demo-contact-copy-column',
                    ],
                    'widgets' => [
                        ['widget_key' => $demoPageContentBlock->key],
                    ],
                ],
                'contact-form' => [
                    'meta' => [
                        'colspan' => 5,
                        'spacing' => 'lg',
                        'html_class' => 'capell-demo-contact-form-column',
                    ],
                    'widgets' => [
                        [
                            'widget_key' => 'contact-form',
                            'form_handle' => 'contact',
                        ],
                    ],
                ],
                'bottom-banner' => [
                    'meta' => [
                        'colspan' => 12,
                        'spacing' => 'none',
                        'container' => 'full',
                    ],
                    'widgets' => [
                        ['widget_key' => $pageBottomBannerBlock->key],
                    ],
                ],
            ],
            'meta' => [
                'description' => 'A standalone contact layout without child or latest-page rails.',
            ],
            'default' => false,
            'status' => true,
        ];

        $layout = $this->layoutModel::query()->firstOrCreate(['key' => 'contact-standalone'], $attributes);
        $layout->forceFill($attributes)->save();

        return $layout;
    }

    protected function demoPageLayout(string $key, string $name, bool $withBreadcrumbs, bool $withHero): Layout
    {
        $demoPageContentBlock = $this->ensureDemoPageContentBlock();
        $pageBottomBannerBlock = $this->ensurePageBottomBannerBlock();
        $heroBlock = $withHero ? CreateHeroBlockAction::run('demo-page-hero', 'Demo Page Hero', 'small') : null;
        $shouldShowBottomBanner = in_array($key, ['capell-demo-single-post', 'capell-demo-platform-architecture'], true);

        $blocks = $withBreadcrumbs
            ? [
                ...($heroBlock !== null ? [['widget_key' => $heroBlock->key]] : []),
                ['widget_key' => 'breadcrumbs'],
                [
                    'widget_key' => $demoPageContentBlock->key,
                    'meta' => [
                        'page_content' => ['content'],
                    ],
                ],
            ]
            : [
                ...($heroBlock !== null ? [['widget_key' => $heroBlock->key]] : []),
                [
                    'widget_key' => $demoPageContentBlock->key,
                    'meta' => [
                        'page_content' => ['content'],
                    ],
                ],
            ];

        $attributes = [
            'name' => $name,
            'group' => 'default',
            'containers' => [
                'main' => [
                    'meta' => [
                        'colspan' => 12,
                        'spacing' => 'lg',
                    ],
                    'widgets' => $blocks,
                ],
                ...($shouldShowBottomBanner ? [
                    'bottom-banner' => [
                        'meta' => [
                            'colspan' => 12,
                            'spacing' => 'none',
                            'container' => 'full',
                        ],
                        'widgets' => [
                            ['widget_key' => $pageBottomBannerBlock->key],
                        ],
                    ],
                ] : []),
            ],
            'meta' => [
                'description' => 'A Capell demo page template rendered through reusable page-content layout blocks.',
            ],
            'default' => false,
            'status' => true,
        ];

        $layout = $this->layoutModel::query()->firstOrCreate(['key' => $key], $attributes);
        $layout->forceFill($attributes)->save();

        return $layout;
    }

    protected function ensureContactFormIntegration(Site $site): void
    {
        $formBuilderNamespace = 'FormBuilder';

        /** @var class-string<Model> $formModel */
        $formModel = sprintf('Capell\%s\Models\Form', $formBuilderNamespace);

        if (! CapellCore::isPackageInstalled(self::FormBuilderPackage)
            || ! class_exists($formModel)
            || ! Schema::hasTable('forms')) {
            return;
        }

        $formModel::query()->updateOrCreate(
            [
                'site_id' => $site->getKey(),
                'handle' => 'contact',
            ],
            [
                'name' => 'Contact',
                'description' => 'Public contact form for Capell enquiries.',
                'schema' => [
                    [
                        'key' => 'name',
                        'label' => 'Name',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'key' => 'email',
                        'label' => 'Email',
                        'type' => 'email',
                        'required' => true,
                        'validation_rules' => ['email'],
                    ],
                    [
                        'key' => 'topic',
                        'label' => 'What can we help with?',
                        'type' => 'select',
                        'required' => true,
                        'options' => [
                            'cms-project' => 'CMS project',
                            'migration' => 'Migration',
                            'integration' => 'Package integration',
                            'support' => 'Support',
                        ],
                    ],
                    [
                        'key' => 'message',
                        'label' => 'Message',
                        'type' => 'textarea',
                        'required' => true,
                    ],
                    [
                        'key' => 'company_website',
                        'label' => 'Company website',
                        'type' => 'honeypot',
                    ],
                ],
                'settings' => [
                    'success_message' => 'Thanks, your message has been sent.',
                    'store_submissions' => true,
                    'notification_email' => 'hello@capell.app',
                    'collect_ip_address' => true,
                    'collect_user_agent' => true,
                ],
                'is_active' => true,
            ],
        );

        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', BlockTypeEnum::Default);

        $blockType ??= $this->typeModel::query()
            ->where('type', LayoutTypeEnum::Widget->value)
            ->firstWhere('key', BlockTypeEnum::Default->value);

        $blockType ??= resolve(TypeCreator::class)->defaultBlockType();

        if (! $blockType instanceof Blueprint) {
            return;
        }

        Widget::query()->updateOrCreate(
            ['key' => 'contact-form'],
            [
                'name' => 'Contact form',
                'blueprint_id' => $blockType->getKey(),
                'component' => 'capell-form-builder::block.form',
                'is_livewire' => true,
                'meta' => [
                    'component' => 'capell-form-builder::block.form',
                    'form_handle' => 'contact',
                ],
                'status' => true,
            ],
        );
    }

    protected function createHomepageBladeBlock(string $key, string $name): Widget
    {
        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', BlockTypeEnum::Default);

        $blockType ??= $this->typeModel::query()
            ->where('type', LayoutTypeEnum::Widget->value)
            ->firstWhere('key', BlockTypeEnum::Default->value);

        $blockType ??= resolve(TypeCreator::class)->defaultBlockType();

        throw_unless($blockType instanceof Blueprint, Exception::class, 'Unable to find default block type.');

        $attributes = [
            'name' => $name,
            'blueprint_id' => $blockType->id,
            'component' => DemoKitServiceProvider::HomepageSectionRenderable,
            'view_file' => null,
            'meta' => [
                'component' => DemoKitServiceProvider::HomepageSectionRenderable,
                'margin' => ['none'],
            ],
        ];

        $block = Widget::query()->firstOrCreate(['key' => $key], $attributes);
        $block->forceFill($attributes)->save();

        foreach (Site::getDefault()->languages ?? [] as $language) {
            $block->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => null,
                    'content' => null,
                ],
            );
        }

        return $block;
    }

    protected function ensurePageBottomBannerBlock(): Widget
    {
        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', BlockTypeEnum::Default);

        $blockType ??= $this->typeModel::query()
            ->where('type', LayoutTypeEnum::Widget->value)
            ->firstWhere('key', BlockTypeEnum::Default->value);

        $blockType ??= resolve(TypeCreator::class)->defaultBlockType();

        throw_unless($blockType instanceof Blueprint, Exception::class, 'Unable to find default block type.');

        $attributes = [
            'name' => 'Page bottom banner',
            'blueprint_id' => $blockType->id,
            'component' => BlockComponentEnum::Default->value,
            'view_file' => null,
            'meta' => [
                'component' => BlockComponentEnum::Default->value,
                'container' => 'full',
                'margin' => ['t-xl'],
                'padding' => ['lg'],
                'background_color' => 'dark-gray',
                'color_scheme' => 'light',
                'align' => 'center',
                'title' => 'Reusable layouts keep the next step consistent',
                'copy' => 'This shared banner can sit below contact pages, articles, or any long-form route that needs a clean break before the footer.',
            ],
            'status' => true,
        ];

        $block = Widget::query()->firstOrCreate(['key' => 'page-bottom-banner'], $attributes);
        $block->forceFill($attributes)->save();

        foreach (Site::getDefault()->languages ?? [] as $language) {
            $block->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => $attributes['meta']['title'],
                    'content' => '<p>' . e($attributes['meta']['copy']) . '</p>',
                ],
            );
        }

        return $block;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function demoPageMeta(string $name): array
    {
        $name = $this->canonicalDemoPageName($name);

        $withoutHero = in_array($name, [
            'Contact',
            'FAQ',
            'Implementation',
            'Project Detail',
            'Home, Buildings and Architecture',
            'Compliance',
            'Sustainability',
        ], true) || in_array($name, self::StandardFooterPageNames, true);

        return [
            'show_hero' => ! $withoutHero,
            'hero_style' => match ($name) {
                'Homepage 2' => 'immersive',
                'About Us', 'Services', 'Team', 'Testimonials', 'Projects', 'Blog', 'Resources', 'Pricing' => 'compact',
                default => 'default',
            },
            'hero_asset_source' => 'mixed',
            'header_over_hero' => $name === 'Homepage 2',
        ];
    }

    protected function demoPageContent(string $name, string $languageCode): ?string
    {
        $name = $this->canonicalDemoPageName($name);

        if ($languageCode !== 'en') {
            return null;
        }

        return $this->basicDemoPageContent($name);
    }

    protected function demoPageHeroContent(string $name, string $content): ?string
    {
        $name = $this->canonicalDemoPageName($name);

        if (in_array($name, [
            'FAQ',
            'Implementation',
            'Project Detail',
            'Home, Buildings and Architecture',
            'Compliance',
            'Sustainability',
        ], true)) {
            return null;
        }

        return '<p>' . e((string) str($content)->stripTags()->before('.')->append('.')->limit(170)) . '</p>';
    }

    protected function demoPageSummary(string $name): ?string
    {
        $name = $this->canonicalDemoPageName($name);

        return match ($name) {
            'Compliance' => 'Regional compliance operations with publishing controls, evidence ownership, and structured local governance.',
            'Sustainability' => 'Local sustainability reporting that keeps regional initiatives, metrics, and proof points consistent across the network.',
            default => null,
        };
    }

    protected function canonicalDemoPageName(string $name): string
    {
        return match (Str::lower($name)) {
            'faq' => 'FAQ',
            'home, buildings and architecture' => 'Platform Architecture',
            'platform architecture' => 'Platform Architecture',
            default => $name,
        };
    }

    protected function basicDemoPageContent(string $name): ?string
    {
        $content = [
            'About Us' => [
                'Capell combines Laravel package discipline, Filament editorial workflows, reusable public blocks, and static delivery into one maintainable publishing platform.',
                'This page pairs portable CMS copy with a reusable public page block so editors can learn where content stops and presentation begins.',
            ],
            'Homepage 2' => [
                'This service-led homepage variation keeps the same Capell content model while changing the public layout rhythm.',
                'Use it to see how a hero, proof modules, route links, and service calls to action can be rearranged without storing designed markup in content.',
            ],
            'Contact' => [
                'Send a message about your CMS project, migration, integration work, or support needs.',
                'The layout separates introduction copy, routing cards, and form fields so qualification can change without rebuilding the template.',
            ],
            'Services' => [
                'Implementation services cover content modelling, migration paths, layout architecture, package boundaries, and launch verification.',
                'The public page combines a split intro, service cards, proof metrics, and a process timeline while this saved content stays deliberately portable.',
            ],
            'Team' => [
                'A team page should prove capability, not just show profiles.',
                'The profile cards are a reusable block pattern: role, focus area, and proof copy can move between team, services, and case-study pages.',
            ],
            'FAQ' => [
                'This page intentionally works without a large hero image.',
                'It proves Capell can render saved page copy, accordion content, and support guidance in a calmer page template.',
            ],
            'Pricing' => [
                'Choose the Capell access and support model that fits your team without hiding implementation risk inside a generic monthly plan.',
                'Plan cards, support notes, migration confidence, and delivery guardrails stay on this route so the homepage can remain compact and focused.',
            ],
            'Testimonials' => [
                'Customer proof should connect outcomes to the delivery model behind them.',
                'The testimonial cards show how quote, role, and outcome data can be reused as proof blocks without baking that layout into the page body.',
            ],
            'Projects' => [
                'Project listings show how Capell can present structured work, media, and calls to action from reusable public templates.',
                'The index layout teaches the pattern: a portable page body first, then project cards, filters, and calls to action from the public block.',
            ],
            'Project Detail' => [
                'A project detail page can explain scope, delivery, results, and ownership without hard-coding the case-study layout into CMS prose.',
                'Capell keeps the rich visual treatment in Blade and the portable story in content.',
            ],
            'Blog' => [
                'Blog listings can use the same editorial rhythm as the rest of the site while staying powered by structured article content.',
                'The page demonstrates a resource-style listing block while storing only simple page copy in the database.',
            ],
            'Home, Buildings and Architecture' => [
                'Blog notes for teams building structured, maintainable Capell websites.',
                'The article page demonstrates metadata, body copy, and editorial chrome without homepage or pricing widgets leaking into the post.',
            ],
            'Platform Architecture' => [
                'Platform architecture pages explain how Capell separates content records, layouts, render data, public components, and package extension points.',
                'The demo keeps the designed surface in package-owned Blade while the saved page body stays portable and reviewable.',
            ],
            'Implementation' => [
                'A productized Capell implementation gives teams a production CMS foundation, migration confidence, and a clear handover path.',
                'This child page shows how scope, timeline, risk, and pricing evidence can sit under the main Pricing page as a dedicated layout block.',
            ],
            'Resources' => [
                'Guides, architecture notes, launch checklists, and developer references for teams building Laravel and Filament CMS platforms with Capell.',
                'The resource hub teaches the CMS pattern: featured content, filters, category cards, latest resources, and toolkit CTA are separate sections.',
            ],
            'Compliance' => [
                'Compliance pages keep regional obligations, policy owners, review cadence, and evidence links close to the local publishing workflow.',
                'Use this child page to prove location content is structured, governed, and reusable.',
            ],
            'Sustainability' => [
                'Sustainability pages give each region room to publish local initiatives while keeping measurement language, media, and taxonomy consistent.',
                'Editors can maintain local proof points without breaking the shared Capell page model.',
            ],
        ];

        if (in_array($name, self::StandardFooterPageNames, true)) {
            $content[$name] = [
                sprintf('%s content is rendered through the shared demo footer page Blade template.', $name),
                'The shared layout teaches a reusable footer-page pattern: portable editorial copy, local proof cards, and consistent navigation structure.',
            ];
        }

        if (! array_key_exists($name, $content)) {
            return null;
        }

        return collect($content[$name])
            ->map(fn (string $paragraph): string => sprintf('<p>%s</p>', e($paragraph)))
            ->implode("\n");
    }

    /**
     * @return Collection<int, Model>
     */
    protected function createFeatures(Site $site): Collection
    {
        $features = [
            [
                'icon' => 'heroicon-o-light-bulb',
                'title' => 'Reusable CMS Patterns',
                'content' => '<p>We use Laravel packages, Filament resources, and reusable blocks to keep CMS implementations maintainable.</p>',
            ],
            [
                'icon' => 'heroicon-o-academic-cap',
                'title' => 'Expertise',
                'content' => '<p>Our team of experts brings deep industry knowledge and experience to every project.</p>',
            ],
            [
                'icon' => 'heroicon-o-user-group',
                'title' => 'Client-Centric Approach',
                'content' => "<p>We prioritize our clients' needs and work collaboratively to achieve their goals.</p>",
            ],
            [
                'icon' => 'heroicon-o-chart-bar',
                'title' => 'Operational Checks',
                'content' => '<p>We ship with checks for content, assets, cache, and frontend output so teams can verify each release.</p>',
            ],
            [
                'icon' => 'heroicon-o-sparkles',
                'title' => 'Sustainable Practices',
                'content' => '<p>We are committed to sustainable practices that benefit our clients and the environment.</p>',
            ],
            [
                'icon' => 'heroicon-o-shield-check',
                'title' => 'Lockdown',
                'content' => '<p>Lock down the public frontend during an incident while keeping break-glass admin access and preserving the live static page cache for recovery.</p>',
            ],
            [
                'icon' => 'heroicon-o-globe-alt',
                'title' => 'Global Reach',
                'content' => '<p>Our global presence allows us to serve clients across diverse markets and industries.</p>',
            ],
        ];

        $layout = Layout::query()->default()->first();
        $defaultPageType = Blueprint::query()
            ->where('type', 'page')
            ->default()
            ->first();

        throw_unless($layout instanceof Layout, Exception::class, 'Default layout not found');
        throw_unless($defaultPageType instanceof Blueprint, Exception::class, 'Default page type not found');

        $parentPage = Page::query()->firstOrNew([
            'site_id' => $site->id,
            'layout_id' => $layout->id,
            'blueprint_id' => $defaultPageType->id,
            'name' => 'Features',
        ]);

        $parentPage->save();

        $site->languages->each(function (Language $language) use ($parentPage): void {
            $parentPage->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => $parentPage->name,
            ]);
        });

        $contentFeatures = new Collection;

        foreach ($features as $feature) {
            $page = Page::query()->firstOrNew([
                'site_id' => $site->id,
                'blueprint_id' => $defaultPageType->id,
                'name' => $feature['title'],
            ]);

            $page->fill([
                'layout_id' => $layout->id,
                'parent_id' => $parentPage->id,
                'meta' => [
                    'icon' => $feature['icon'],
                ],
            ]);

            $page->save();

            $this->createMedia($page);

            $content = $this->contentModel::query()->updateOrCreate([
                'name' => $feature['title'],
            ], [
                'meta' => [
                    'icon' => $feature['icon'],
                    'pageable_id' => $page->id,
                    'pageable_type' => $page->getMorphClass(),
                ],
            ]);

            $this->createMedia($content);

            $contentFeatures->push($content);

            $site->languages->each(function (Language $language) use ($page, $content, $feature): void {
                $page->translations()->firstOrCreate([
                    'language_id' => $language->id,
                ], [
                    'title' => $feature['title'],
                    'content' => $feature['content'],
                ]);

                $this->translationsFor($content)->firstOrCreate([
                    'language_id' => $language->id,
                ], [
                    'title' => $feature['title'],
                    'content' => $feature['content'],
                ]);
            });
        }

        return $contentFeatures;
    }

    /**
     * @param  Collection<int, Model>  $languages
     * @return Collection<int, Model>
     */
    protected function createTestimonials(Collection $languages): Collection
    {
        $testimonialContent = $this->contentModel::query()->firstOrCreate([
            'name' => 'Testimonials',
        ], [
            'meta' => [
                'icon' => 'heroicon-o-chat-bubble-left-right',
            ],
        ]);

        $this->createMedia($testimonialContent);

        $testimonials = [
            [
                'name' => 'John Doe',
                'position' => 'CEO of Example Corp',
                'content' => 'Capell gave our editors a clearer workflow and gave engineering a smaller surface area to maintain.',
            ],
            [
                'name' => 'Jane Smith',
                'position' => 'CTO of Tech Innovations',
                'content' => 'The team at Capell is incredibly knowledgeable and always goes the extra mile for us.',
            ],
            [
                'name' => 'Jeff Wilson',
                'position' => 'Marketing Director at Creative Agency',
                'content' => 'We have seen significant growth since partnering with Capell. Their expertise is unmatched.',
            ],
        ];

        $testimonialsCollection = new Collection;

        $testimonialType = Blueprint::query()->updateOrCreate([
            'key' => 'testimonial',
            'type' => 'section',
        ], [
            'name' => 'Testimonial',
            'admin' => [
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'configurator' => 'testimonial-section',
            ],
        ]);

        foreach ($testimonials as $testimonial) {
            $content = $this->contentModel::query()->firstOrCreate([
                'name' => $testimonial['name'],
                'parent_id' => $testimonialContent->id,
                'blueprint_id' => $testimonialType->id,
            ], [
                'meta' => [
                    'position' => $testimonial['position'],
                ],
            ]);

            $this->createMedia($content);

            $translations = [];

            foreach ($languages as $language) {
                if (! $language instanceof Language) {
                    continue;
                }

                if ($content->translations->contains('language_id', $language->id)) {
                    continue;
                }

                $translations[] = [
                    'language_id' => $language->id,
                    'title' => $testimonial['name'],
                    'content' => sprintf('<p>%s</p>', $testimonial['content']),
                ];
            }

            $this->translationsFor($content)->createMany($translations);

            $testimonialsCollection->push($content);
        }

        return $testimonialsCollection;
    }

    /**
     * @param  Collection<int, Model>  $languages
     * @return Collection<int, Model>
     */
    protected function createTeamMembers(Collection $languages): Collection
    {
        $teamMembers = [
            [
                'name' => 'Alice Johnson',
                'position' => 'CEO',
                'bio' => '<p>Alice coordinates product priorities, release scope, and editorial feedback across the demo team.</p>',
            ],
            [
                'name' => 'Charlie Brown',
                'position' => 'CFO',
                'bio' => '<p>Charlie manages our finances with precision, ensuring sustainable growth and stability.</p>',
            ],
            [
                'name' => 'Fiona Green',
                'position' => 'Head of HR',
                'bio' => "<p>Fiona is dedicated to building a strong team culture and supporting our employees' growth.</p>",
            ],
            [
                'name' => 'George White',
                'position' => 'Lead Designer',
                'bio' => '<p>George turns design requirements into reusable section patterns and practical editorial controls.</p>',
            ],
            [
                'name' => 'Hannah Blue',
                'position' => 'Senior Developer',
                'bio' => '<p>Hannah is a coding wizard, turning complex problems into elegant solutions.</p>',
            ],
            [
                'name' => 'Ian Black',
                'position' => 'Project Manager',
                'bio' => '<p>Ian keeps our projects on track, ensuring timely delivery and client satisfaction.</p>',
            ],
            [
                'name' => 'Julia Red',
                'position' => 'Content Strategist',
                'bio' => '<p>Julia crafts compelling content strategies that engage and inform our audience.</p>',
            ],
            [
                'name' => 'Kevin Yellow',
                'position' => 'Data Analyst',
                'bio' => '<p>Kevin reviews usage data and release checks so the demo keeps reflecting real CMS workflows.</p>',
            ],
            [
                'name' => 'Laura Purple',
                'position' => 'Customer Success Manager',
                'bio' => '<p>Laura gathers editor feedback and keeps onboarding notes clear for new project teams.</p>',
            ],
            [
                'name' => 'Mike Orange',
                'position' => 'Sales Director',
                'bio' => '<p>Mike drives our sales strategy, helping us reach new heights in revenue.</p>',
            ],
            [
                'name' => 'Nina Pink',
                'position' => 'UX Researcher',
                'bio' => '<p>Nina conducts research to understand user needs, shaping our products for better usability.</p>',
            ],
            [
                'name' => 'Oscar Gray',
                'position' => 'IT Support Specialist',
                'bio' => '<p>Oscar keeps our systems running smoothly, providing technical support to our team.</p>',
            ],
            [
                'name' => 'Quentin Silver',
                'position' => 'Business Analyst',
                'bio' => '<p>Quentin analyzes market trends, helping us identify new opportunities for growth.</p>',
            ],
            [
                'name' => 'Sam White',
                'position' => 'Quality Assurance Specialist',
                'bio' => '<p>Sam ensures our products meet the highest quality standards before they reach our clients.</p>',
            ],
            [
                'name' => 'Victor Blue',
                'position' => 'Network Administrator',
                'bio' => '<p>Victor manages our network infrastructure, ensuring reliable connectivity for our team.</p>',
            ],
            [
                'name' => 'Zane Purple',
                'position' => 'Research Scientist',
                'bio' => '<p>Zane tests integration ideas and documents the ones that belong in the package roadmap.</p>',
            ],
        ];

        $teamContent = $this->contentModel::query()->firstOrNew([
            'name' => 'Team Members',
        ]);

        $meta = $teamContent->meta ?? [];
        $meta['icon'] = 'heroicon-o-users';
        $teamContent->meta = $meta;

        $teamContent->save();

        $teamMembersCollection = new Collection;

        foreach ($teamMembers as $member) {
            $content = $this->contentModel::query()->firstOrCreate([
                'name' => $member['name'],
                'parent_id' => $teamContent->id,
            ], [
                'meta' => [
                    'position' => $member['position'],
                ],
            ]);

            $this->createMedia($content);

            $translations = [];

            foreach ($languages as $language) {
                if (! $language instanceof Language) {
                    continue;
                }

                if ($content->translations->contains('language_id', $language->id)) {
                    continue;
                }

                $translations[] = [
                    'language_id' => $language->id,
                    'title' => $member['name'],
                    'content' => $member['bio'],
                ];
            }

            $this->translationsFor($content)->createMany($translations);

            $teamMembersCollection->push($content);
        }

        return $teamMembersCollection;
    }

    protected function createBlockMedia(Widget $model, ?string $name = null, string $type = 'image', BackedEnum|string $collection = MediaCollectionEnum::Image): Media
    {
        // Normalize input name and derive extension if provided
        $inputName = in_array($name, [null, '', '0'], true) ? null : $name;
        $inputExt = $inputName !== null ? pathinfo($inputName, PATHINFO_EXTENSION) : '';

        // Decide base demo path and defaults per type
        $isVideo = $type === 'video';
        $demoPath = static::getDemoResourcePath($isVideo ? 'video' : 'img');

        // Determine filename (without extension) and extension
        $filenameBase = $inputName !== null
            ? pathinfo($inputName, PATHINFO_FILENAME)
            : ($isVideo ? 'SampleVideo_1280x720_1mb' : null);

        $ext = $inputExt !== ''
            ? strtolower($inputExt)
            : ($isVideo ? 'mp4' : 'jpg');

        // Use video collection explicitly
        if ($isVideo) {
            $collection = MediaCollectionEnum::Video;
        }

        // Build the candidate file path
        $demoFile = $filenameBase !== null ? sprintf('%s/%s.%s', $demoPath, $filenameBase, $ext) : '';

        // Fallback handling: if no filename or file missing, choose a random demo image for images
        if ($filenameBase === null || $demoFile === '' || ! file_exists($demoFile)) {
            if ($isVideo) {
                // For videos, keep original demo path and defaults; we'll still attach a poster image below
                // Attempt video default file first
                $filenameBase = 'SampleVideo_1280x720_1mb';
                $ext = $inputExt !== '' ? strtolower($inputExt) : 'mp4';
            } else {
                // For images: pick a random demo image and set explicit jpg (demo images are jpg)
                $demoPath = static::getDemoResourcePath('img');
                $filenameBase = $this->getRandomDemoImage($demoPath, 'jpg');
                $ext = 'jpg';
            }

            $demoFile = sprintf('%s/%s.%s', $demoPath, $filenameBase, $ext);
        }

        throw_unless(File::exists($demoFile), Exception::class, 'Unable to find demo media file: ' . $demoFile);

        // Create content and link via WidgetAsset
        $content = $this->contentModel::query()->create([
            'name' => str($filenameBase)->title(),
        ]);
        assert($content instanceof HasMedia);

        $model->assets()->create([
            'asset_id' => $content->getKey(),
            'asset_type' => resolve($this->contentModel)->getMorphClass(),
        ]);

        gc_collect_cycles();

        $media = $content->addMedia($demoFile)
            ->preservingOriginal()
            ->withCustomProperties($isVideo ? [] : $this->imageDimensions($demoFile))
            ->toMediaCollection($collection instanceof BackedEnum ? $collection->value : $collection);

        gc_collect_cycles();

        // For videos, also attach a jpg poster image
        if (! $isVideo) {
            return $this->ensureCapellMedia($media);
        }

        $posterPath = static::getDemoResourcePath('img');
        $posterBase = $this->getRandomDemoImage($posterPath);
        $posterFile = sprintf('%s/%s.jpg', $posterPath, $posterBase);

        if (! File::exists($posterFile)) {
            return $this->ensureCapellMedia($media);
        }

        $posterMedia = $content->addMedia($posterFile)
            ->preservingOriginal()
            ->withCustomProperties($this->imageDimensions($posterFile))
            ->toMediaCollection(MediaCollectionEnum::Image->value);

        gc_collect_cycles();

        return $this->ensureCapellMedia($posterMedia);
    }

    /**
     * @return array{width: int, height: int}|array{}
     */
    protected function imageDimensions(string $path): array
    {
        $dimensions = @getimagesize($path);

        if (! is_array($dimensions)) {
            return [];
        }

        return [
            'width' => $dimensions[0],
            'height' => $dimensions[1],
        ];
    }

    protected function ensureCapellMedia(SpatieMedia $media): Media
    {
        throw_unless($media instanceof Media, RuntimeException::class, 'Demo media creation must return a Capell media model.');

        return $media;
    }

    /**
     * @template TValue
     *
     * @param  non-empty-list<TValue>  $items
     * @return TValue
     */
    protected function randomItem(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}
