<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use BackedEnum;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Media;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\LayoutBuilder\Actions\CreateHeroBlockAction;
use Capell\LayoutBuilder\Enums\BlockTypeEnum;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Models\Block;
use Capell\LayoutBuilder\Models\BlockAsset;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Capell\Navigation\Support\Creator\NavigationCreator;
use Error;
use Exception;
use FilesystemIterator;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Image\Image;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use SplFileInfo;
use Throwable;
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

    /** @var class-string<Models\Translation> */
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

    /** @var class-string<Block> */
    protected string $blockModel;

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
    public function createMedia(Model&HasMedia $model, ?string $name = null, string $type = 'image', BackedEnum|string $collection = MediaCollectionEnum::Image): void
    {
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

        $image = null;
        if ($type !== 'video') {
            try {
                $image = Image::load($demo_file);
            } catch (Throwable) {
                $image = null;
            }
        }

        $customProps = [
            ...(
                $image instanceof Image
                ? ['width' => $image->getWidth(), 'height' => $image->getHeight()]
                : []
            ),
        ];

        if (! File::exists($demo_file)) {
            return;
        }

        try {
            $model->addMedia($demo_file)
                ->preservingOriginal()
                ->withCustomProperties($customProps)
                ->toMediaCollection($this->mediaCollectionName($collection));
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

    protected function attachRelatedSites(Site $defaultSite, Collection $sites): void
    {
        $defaultSite->related()
            ->attach($sites->where('id', '!=', $defaultSite->id))
            ->save();
    }

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

    protected function navigationPageItems(Collection $siteTree, Language $language): array
    {
        $items = [];

        foreach ($siteTree as $page) {
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

        if (CapellCore::isPackageInstalled(self::NavigationPackage) && class_exists($navigationCreator) && method_exists($navigationCreator, 'getPageNavigationLabel')) {
            return $navigationCreator::getPageNavigationLabel($page, $language);
        }

        return $page->translation?->title ?? $page->name;
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

    protected function translationsFor(Model $model): HasMany|MorphMany
    {
        /** @phpstan-ignore-next-line method.notFound */
        return $model->translations();
    }

    protected function createPageBlockAsset(Block $block, Pageable $page, string $container, int $occurrence, Model $asset): BlockAsset
    {
        return DB::transaction(
            fn (): BlockAsset => $block->assets()->createOrFirst([
                'pageable_id' => $page->getKey(),
                'pageable_type' => $page->getMorphClass(),
                'container' => $container,
                'occurrence' => $occurrence,
                'asset_type' => $asset->getMorphClass(),
                'asset_id' => $asset->getKey(),
            ]),
            attempts: 5,
        );
    }

    protected function ensureDemoPageContentBlock(): Block
    {
        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
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

        $block = Block::query()->firstOrCreate(['key' => 'demo-page-content'], $attributes);
        $block->forceFill($attributes)->save();

        return $block;
    }

    protected function layoutForDemoPage(string $name): ?Layout
    {
        $name = $this->canonicalDemoPageName($name);
        $demoPageContentBlock = $this->ensureDemoPageContentBlock();

        $templateLayouts = [
            'About Us' => ['capell-demo-about', 'Capell Demo About', true],
            'Homepage 2' => ['capell-demo-homepage-2', 'Capell Demo Homepage 2', false],
            'Services' => ['capell-demo-services', 'Capell Demo Services', true],
            'Team' => ['capell-demo-team', 'Capell Demo Team', true],
            'FAQ' => ['capell-demo-faq-no-hero', 'Capell Demo FAQ Without Hero', true],
            'Pricing' => ['capell-demo-pricing-no-hero', 'Capell Demo Pricing Without Hero', true],
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
                ! in_array($name, ['FAQ', 'Pricing', 'Project Detail'], true),
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
                            'blocks' => [
                                ['block_key' => 'breadcrumbs'],
                                ['block_key' => $demoPageContentBlock->key],
                            ],
                        ],
                    ],
                    'blocks' => ['breadcrumbs', $demoPageContentBlock->key],
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
                    'blocks' => [
                        ['block_key' => 'breadcrumbs'],
                    ],
                ],
                'contact-copy' => [
                    'meta' => [
                        'colspan' => 7,
                        'spacing' => 'lg',
                        'html_class' => 'capell-demo-contact-copy-column',
                    ],
                    'blocks' => [
                        ['block_key' => $demoPageContentBlock->key],
                    ],
                ],
                'contact-form' => [
                    'meta' => [
                        'colspan' => 5,
                        'spacing' => 'lg',
                        'html_class' => 'capell-demo-contact-form-column',
                    ],
                    'blocks' => [
                        [
                            'block_key' => 'contact-form',
                            'form_handle' => 'contact',
                        ],
                    ],
                ],
            ],
            'blocks' => ['breadcrumbs', $demoPageContentBlock->key, 'contact-form'],
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
        $heroBlock = $withHero ? CreateHeroBlockAction::run('demo-page-hero', 'Demo Page Hero', 'small') : null;

        $blocks = $withBreadcrumbs
            ? [
                ...($heroBlock !== null ? [['block_key' => $heroBlock->key]] : []),
                ['block_key' => 'breadcrumbs'],
                [
                    'block_key' => $demoPageContentBlock->key,
                    'meta' => [
                        'page_content' => ['content'],
                    ],
                ],
            ]
            : [
                ...($heroBlock !== null ? [['block_key' => $heroBlock->key]] : []),
                [
                    'block_key' => $demoPageContentBlock->key,
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
                    'blocks' => $blocks,
                ],
            ],
            'blocks' => collect($blocks)
                ->pluck('block_key')
                ->values()
                ->all(),
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

        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
            ->firstWhere('key', BlockTypeEnum::Default);

        $blockType ??= $this->typeModel::query()
            ->where('type', LayoutTypeEnum::Block->value)
            ->firstWhere('key', BlockTypeEnum::Default->value);

        if (! $blockType instanceof Blueprint) {
            return;
        }

        Block::query()->updateOrCreate(
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

    protected function createHomepageBladeBlock(string $key, string $name): Block
    {
        $blockType = $this->typeModel::query()->where('type', LayoutTypeEnum::Block)
            ->firstWhere('key', BlockTypeEnum::Default);

        $blockType ??= $this->typeModel::query()
            ->where('type', LayoutTypeEnum::Block->value)
            ->firstWhere('key', BlockTypeEnum::Default->value);

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

        $block = Block::query()->firstOrCreate(['key' => $key], $attributes);
        $block->forceFill($attributes)->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
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

    /**
     * @return array<string, mixed>
     */
    protected function demoPageMeta(string $name): array
    {
        $name = $this->canonicalDemoPageName($name);

        $withoutHero = in_array($name, [
            'FAQ',
            'Pricing',
            'Implementation',
            'Project Detail',
            'Home, Buildings and Architecture',
            'Compliance',
            'Sustainability',
        ], true);

        return [
            'show_hero' => ! $withoutHero,
            'hero_style' => match ($name) {
                'Homepage 2' => 'immersive',
                'About Us', 'Services', 'Team', 'Testimonials', 'Projects', 'Blog', 'Resources' => 'compact',
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
            'Pricing',
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
                'Editors get flexible composition. Developers keep clear boundaries. Visitors receive clean, fast public output.',
            ],
            'Homepage 2' => [
                'This service-led homepage variation keeps the same Capell content model while changing the public layout rhythm.',
                'Use it to prove that page records can be rendered through different package-owned templates without storing designed markup in content.',
            ],
            'Contact' => [
                'Send a message about your CMS project, migration, integration work, or support needs.',
                'A clear contact page keeps the next step simple without sending visitors through child pages.',
            ],
            'Services' => [
                'Implementation services cover content modelling, migration paths, layout architecture, package boundaries, and launch verification.',
                'The public page is rendered by the demo page-content block while this saved content stays deliberately portable.',
            ],
            'Team' => [
                'A team page should prove capability, not just show profiles.',
                'These roles map to the work needed to build flexible Capell sites.',
            ],
            'FAQ' => [
                'This page intentionally works without a large hero image.',
                'It proves Capell can render dense support content in a calmer page template.',
            ],
            'Pricing' => [
                'Choose the access and support model that fits your team.',
                'Start with a developer plan for evaluation, move to agency support for production delivery, or scope an enterprise agreement when governance and response times matter.',
            ],
            'Testimonials' => [
                'Customer proof should connect outcomes to the delivery model behind them.',
                'The demo template renders testimonials as a public proof surface without baking that layout into the page body.',
            ],
            'Projects' => [
                'Project listings show how Capell can present structured work, media, and calls to action from reusable public templates.',
                'The saved content remains plain enough to survive editor and renderer changes.',
            ],
            'Project Detail' => [
                'A project detail page can explain scope, delivery, results, and ownership without hard-coding the case-study layout into CMS prose.',
                'Capell keeps the rich visual treatment in Blade and the portable story in content.',
            ],
            'Blog' => [
                'Blog listings can use the same editorial rhythm as the rest of the site while staying powered by structured article content.',
                'This demo page keeps presentation in Blade and stores only simple page copy.',
            ],
            'Home, Buildings and Architecture' => [
                'Blog notes for teams building structured, maintainable Capell websites.',
                'The article page demonstrates a focused editorial layout without homepage or pricing widgets leaking into the post.',
            ],
            'Platform Architecture' => [
                'Platform architecture pages explain how Capell separates content records, layouts, render data, public components, and package extension points.',
                'The demo keeps the designed surface in package-owned Blade while the saved page body stays portable and reviewable.',
            ],
            'Implementation' => [
                'A productized Capell implementation gives teams a production CMS foundation, migration confidence, and a clear handover path.',
                'Every scope change gets priced before work starts.',
            ],
            'Resources' => [
                'Guides, architecture notes, launch checklists, and developer references for teams building Laravel and Filament CMS platforms with Capell.',
                'The resource library page uses a custom demo Blade surface instead of storing a designed index in the database.',
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
                'The database stores portable editorial copy while the package view owns the designed public presentation.',
            ];
        }

        if (! array_key_exists($name, $content)) {
            return null;
        }

        return collect($content[$name])
            ->map(fn (string $paragraph): string => sprintf('<p>%s</p>', e($paragraph)))
            ->implode("\n");
    }

    protected function contactIndexContent(): string
    {
        return <<<'HTML'
<section class="capell-demo-contact-gateway">
    <p class="capell-demo-eyebrow">Contact gateway</p>
    <h2>Send a message about your Capell build</h2>
    <p>Route implementation, migration, package, and support enquiries through one clear public surface.</p>
    <div class="capell-demo-contact-grid">
        <article><span>Address</span><strong>Capell Studio, London</strong><p>Remote-first delivery with UK timezone handover.</p></article>
        <article><span>Response</span><strong>Two business days</strong><p>Enough context to qualify the right delivery path.</p></article>
        <article><span>Routing</span><strong>Project, support, migration</strong><p>Contact topics map to the same governed CMS model.</p></article>
    </div>
</section>
HTML;
    }

    protected function showcaseAboutContent(): string
    {
        return $this->showcasePageContent(
            'about',
            'Platform experience',
            'Experienced in flexible content systems',
            'Use Capell when a site needs more than pages and prose. The same model can power media-heavy marketing pages, resource libraries, navigation-led microsites, and governed multi-site publishing.',
            [
                ['01', 'Model content', 'Store durable page stories as simple CMS content that can move between renderers.'],
                ['02', 'Render in Blade', 'Keep the designed public surface inside package-owned views.'],
                ['03', 'Verify output', 'Connect admin records to frontend rendering without exposing editor concerns.'],
            ],
        );
    }

    protected function showcaseHomepageTwoContent(): string
    {
        return $this->showcasePageContent(
            'home-variant',
            'Homepage variant',
            'A second homepage for service-led Capell builds',
            'This page proves the same content system can support a different homepage rhythm: stronger service positioning, proof modules, and route-specific calls to action.',
            [
                ['Hero', 'Service-led opening', 'A compact proposition for teams evaluating implementation support.'],
                ['Proof', 'Capability modules', 'Reusable proof cards make the page feel distinct without another template stack.'],
                ['Routes', 'Next-step links', 'Pricing, contact, resources, and services stay connected from the variant.'],
            ],
        );
    }

    protected function contactServicesContent(): string
    {
        return <<<'HTML'
<section class="capell-demo-services-atelier">
    <p class="capell-demo-eyebrow">Services atelier</p>
    <h2>Implementation services for complex Capell rollouts</h2>
    <p>Content modelling, migration paths, layout architecture, package boundaries, and launch verification stay connected in one delivery path.</p>
    <div class="capell-demo-service-board">
        <article><span>Audit board</span><strong>Content model review</strong><p>Map pages, assets, routes, redirects, and ownership before implementation starts.</p></article>
        <article><span>Build board</span><strong>Layout architecture</strong><p>Create reusable blocks that editors can compose without breaking public output.</p></article>
        <article><span>Launch board</span><strong>Release checks</strong><p>Verify cache, navigation, search, SEO, and anonymous page safety before handover.</p></article>
    </div>
</section>
HTML;
    }

    protected function showcaseTeamContent(): string
    {
        return $this->showcasePageContent(
            'team',
            'Delivery team',
            'Implementation specialists for Capell websites',
            'A team page should prove capability, not just show profiles. These roles map to the work needed to build flexible Capell sites.',
            [
                ['Strategy', 'CMS architecture', 'Owns page models, routes, package boundaries, and release shape.'],
                ['Frontend', 'Public rendering', 'Builds Tailwind and Blade surfaces that stay clean for visitors.'],
                ['Publishing', 'Workflow setup', 'Connects Filament editing, preview, approval, and handover.'],
            ],
        );
    }

    protected function showcaseFaqContent(): string
    {
        return <<<'HTML'
<section class="capell-demo-showcase-page capell-demo-showcase-page--faq">
    <p class="capell-demo-eyebrow">Support layout</p>
    <h2>FAQ content without a hero dependency</h2>
    <details open><summary>Can a page skip the hero entirely?</summary><p>Yes. Pages can render directly into support, article, pricing, or project layouts without needing a hero block.</p></details>
    <details><summary>Where does the designed markup live?</summary><p>The demo page-content block owns the Blade presentation. The database stores portable content only.</p></details>
    <details><summary>Can editors still update the copy?</summary><p>Yes. The saved page content renders before the template-specific proof modules.</p></details>
</section>
HTML;
    }

    protected function pricingIndexContent(): string
    {
        return <<<'HTML'
<section class="capell-demo-pricing-matrix">
    <p class="capell-demo-eyebrow">Pricing matrix</p>
    <h2>Simple pricing for Capell CMS delivery</h2>
    <p>Compare the commercial model without making the homepage carry the full pricing table.</p>
    <div class="capell-demo-pricing-grid">
        <article><span>Developer</span><strong>GBP 0</strong><p>For evaluation, prototypes, and local proof-of-concept work.</p><em>Self-guided</em></article>
        <article class="is-featured"><span>Agency</span><strong>GBP 99</strong><p>For production delivery with commercial support and implementation confidence.</p><em>Popular</em></article>
        <article><span>Enterprise</span><strong>Custom</strong><p>For governed estates, multi-site publishing, and dedicated support paths.</p><em>Scoped</em></article>
    </div>
    <section class="capell-demo-pricing-questions"><h3>Common pricing questions</h3><p>Support level, response time, migration help, and implementation depth are separated so teams can pick the right path.</p></section>
</section>
HTML;
    }

    protected function showcaseTestimonialsContent(): string
    {
        return $this->showcasePageContent(
            'testimonials',
            'Customer proof',
            'What Capell builders say',
            'Customer proof should connect outcomes to the delivery model behind them.',
            [
                ['Agency', 'Faster rebuilds', 'Reusable blocks reduced one-off template work across the site.'],
                ['Editor', 'Clear ownership', 'Teams can update copy and media without touching implementation details.'],
                ['Engineering', 'Cleaner releases', 'Public output remains cacheable and separate from admin tooling.'],
            ],
        );
    }

    protected function showcaseProjectsContent(): string
    {
        return $this->showcasePageContent(
            'projects',
            'Project library',
            'Capell implementation project library',
            'Project listings show how Capell can present structured work, media, and calls to action from reusable public templates.',
            [
                ['Case study', 'Layout builder redesign', 'A flexible page system rebuilt around reusable sections and assets.'],
                ['Migration', 'Resource library import', 'Structured content and redirects moved into a governed CMS workflow.'],
                ['Launch', 'Static delivery rollout', 'Cache generation and public verification before handover.'],
            ],
        );
    }

    protected function showcaseProjectDetailContent(): string
    {
        return $this->showcasePageContent(
            'project-detail',
            'Project detail',
            'Layout builder redesign for a flexible Capell website',
            'A project detail page can explain scope, delivery, results, and ownership without hard-coding the case-study layout into CMS prose.',
            [
                ['Scope', 'Reusable page sections', 'The implementation kept existing content intent while improving layout ownership.'],
                ['Result', 'Cleaner publishing', 'Editors gained safer composition and developers kept package-owned rendering.'],
                ['Handover', 'Documented release path', 'QA, cache, and frontend checks are part of the delivery.'],
            ],
        );
    }

    protected function showcaseBlogContent(): string
    {
        return $this->showcasePageContent(
            'blog',
            'Latest news',
            'Our blog for Capell builders',
            'Blog listings can use the same editorial rhythm as the rest of the site while staying powered by structured article content.',
            [
                ['News', 'Home, buildings and architecture', 'How architecture-style page systems map to Capell layout builder websites.'],
                ['Guide', 'Designing a better homepage flow', 'Turning mixed CMS objects into one coherent public page.'],
                ['Tips', 'How to avoid rigid templates', 'Use block boundaries, assets, and reusable sections to keep pages flexible.'],
            ],
        );
    }

    protected function showcaseSinglePostContent(): string
    {
        return <<<'HTML'
<article class="capell-demo-showcase-page capell-demo-showcase-page--single-post">
    <p class="capell-demo-eyebrow">Article template</p>
    <h2>Home, buildings and architecture</h2>
    <p>Blog notes for teams building structured, maintainable Capell websites.</p>
    <aside><strong>Article chrome</strong><span>Author metadata, body copy, related resources, and clean public rendering.</span></aside>
</article>
HTML;
    }

    protected function implementationPricingContent(): string
    {
        return <<<'HTML'
<section class="capell-demo-implementation-plan">
    <p class="capell-demo-eyebrow">Implementation scoping</p>
    <h2>Implementation plan with commercial guardrails</h2>
    <p>Turn scope, timeline, risk, and price confidence into a visible delivery surface.</p>
    <div class="capell-demo-implementation-grid">
        <article><span>Scope confidence</span><strong>High</strong><p>Known page types, content model, integrations, and launch criteria.</p></article>
        <article><span>Delivery rhythm</span><strong>4 phases</strong><p>Audit, build, migrate, verify.</p></article>
        <article><span>Guardrails</span><strong>Change controlled</strong><p>Commercial changes are priced before implementation work starts.</p></article>
    </div>
</section>
HTML;
    }

    protected function locationsIndexContent(): string
    {
        return $this->footerPageContent(
            'locations',
            'Locations',
            'Multi-site delivery without losing local context',
            'Network signal',
            'Operational proof',
            'Regional teams can publish local obligations, evidence, and support details while sharing the same Capell rendering system.',
        );
    }

    protected function integrationsIndexContent(): string
    {
        return $this->footerPageContent(
            'integrations',
            'Integrations',
            'Integration surfaces for teams that need traceable sync',
            'Connector map',
            'Sync health',
            'Show how package integration status, connector ownership, and data movement are explained to visitors.',
        );
    }

    protected function partnersIndexContent(): string
    {
        return $this->footerPageContent(
            'partners',
            'Partners',
            'Partner delivery paths with clear implementation boundaries',
            'Partner ladder',
            'Delivery proof',
            'Partner pages explain routes to market, support ownership, and when a project moves from referral to implementation.',
        );
    }

    protected function roadmapIndexContent(): string
    {
        return $this->footerPageContent(
            'roadmap',
            'Roadmap',
            'A roadmap page that turns product direction into trust',
            'Release board',
            'Decision log',
            'Roadmap content keeps upcoming platform work, delivery confidence, and constraints visible without promising vague features.',
        );
    }

    protected function governanceIndexContent(): string
    {
        return $this->footerPageContent(
            'governance',
            'Governance',
            'Governance content for teams that publish with consequences',
            'Control panel',
            'Audit trail',
            'Governance pages make permissions, approval flow, content ownership, and release responsibilities explicit.',
        );
    }

    protected function trainingIndexContent(): string
    {
        return $this->footerPageContent(
            'training',
            'Training',
            'Training pages that help teams actually own the CMS',
            'Training map',
            'Handover proof',
            'Training content turns editor onboarding, support paths, and repeatable publishing habits into a visible page.',
        );
    }

    protected function complianceLocationContent(): string
    {
        return <<<'HTML'
<section class="capell-demo-location-detail">
    <p class="capell-demo-eyebrow">Location detail</p>
    <h2>Compliance content for regional obligations</h2>
    <p>Local teams can explain regional obligations, review cadence, policy ownership, and evidence without changing the shared footer template.</p>
</section>
HTML;
    }

    protected function sustainabilityLocationContent(): string
    {
        return <<<'HTML'
<section class="capell-demo-location-detail">
    <p class="capell-demo-eyebrow">Location detail</p>
    <h2>Sustainability content for local initiatives</h2>
    <p>Local initiatives, measurements, and proof points stay consistent across the network while remaining editable by regional owners.</p>
</section>
HTML;
    }

    protected function resourcesHubContent(): string
    {
        return <<<'HTML'
<section class="capell-demo-resources-library">
    <p class="capell-demo-eyebrow">Resource library</p>
    <h2>Resource library for Capell builders</h2>
    <p>Resource index pages need dense but readable cards, filters, article metadata, and implementation references.</p>
    <div class="capell-demo-resource-index">
        <article><span>Migration</span><h3>Designing imports editors can trust</h3><p>Validate source rows, preserve redirects, and keep rejected records explainable.</p><em>9 min</em></article>
        <article><span>Publishing</span><h3>Approval workflows without admin leakage</h3><p>Keep draft tooling private while public pages stay clean and cacheable.</p><em>7 min</em></article>
        <article><span>Theme systems</span><h3>Package-owned frontend rendering</h3><p>Build reusable public surfaces without coupling them to Filament screens.</p><em>11 min</em></article>
    </div>
</section>
HTML;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $items
     */
    protected function showcasePageContent(string $slug, string $eyebrow, string $title, string $intro, array $items): string
    {
        $cards = collect($items)
            ->map(fn (array $item): string => sprintf(
                '<article><span>%s</span><h3>%s</h3><p>%s</p></article>',
                e($item[0]),
                e($item[1]),
                e($item[2]),
            ))
            ->implode('');

        return sprintf(
            '<section class="capell-demo-showcase-page capell-demo-showcase-page--%s"><p class="capell-demo-eyebrow">%s</p><h2>%s</h2><p>%s</p><div class="capell-demo-showcase-grid">%s</div></section>',
            e($slug),
            e($eyebrow),
            e($title),
            e($intro),
            $cards,
        );
    }

    protected function footerPageContent(string $slug, string $eyebrow, string $title, string $signalLabel, string $proofLabel, string $copy): string
    {
        return sprintf(
            '<section class="capell-demo-footer-page capell-demo-footer-page--%s"><div class="capell-demo-footer-editorial"><p class="capell-demo-eyebrow">%s</p><h2>%s</h2><p>%s</p></div><div class="capell-demo-footer-evidence"><article><span>%s</span><strong>Mapped</strong><p>Content, routes, and ownership are visible.</p></article><article><span>%s</span><strong>Verified</strong><p>Public output can be checked before handover.</p></article></div><div class="capell-demo-footer-variation-strip"><span>Footer route</span><span>Shared template</span><span>Local content</span></div></section>',
            e($slug),
            e($eyebrow),
            e($title),
            e($copy),
            e($signalLabel),
            e($proofLabel),
        );
    }

    protected function createFeatures(Site $site): Collection
    {
        $features = [
            [
                'icon' => 'heroicon-o-light-bulb',
                'title' => 'Innovative Solutions',
                'content' => '<p>We leverage cutting-edge technology to create innovative solutions that drive success.</p>',
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
                'title' => 'Measurable Results',
                'content' => '<p>We focus on delivering measurable results that drive growth and success.</p>',
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

        throw_unless($layout instanceof Layout, Exception::class, 'Default layout not found');

        $parentPage = Page::query()->firstOrNew([
            'site_id' => $site->id,
            'layout_id' => $layout->id,
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
                'name' => $feature['title'],
            ]);

            $page->fill([
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
                'content' => 'Capell has transformed our business with their innovative solutions and exceptional service.',
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

            $this->translationsFor($content)->createMany(
                $languages
                    ->reject(fn (Language $language): bool => $content->translations->contains('language_id', $language->id))
                    ->map(fn (Language $language): array => [
                        'language_id' => $language->id,
                        'title' => $testimonial['name'],
                        'content' => sprintf('<p>%s</p>', $testimonial['content']),
                    ])
                    ->all(),
            );

            $testimonialsCollection->push($content);
        }

        return $testimonialsCollection;
    }

    protected function createTeamMembers(Collection $languages): Collection
    {
        $teamMembers = [
            [
                'name' => 'Alice Johnson',
                'position' => 'CEO',
                'bio' => '<p>Alice is the visionary behind our success, leading the team with passion and expertise.</p>',
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
                'bio' => '<p>George brings creativity and innovation to our design projects, making them visually stunning.</p>',
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
                'bio' => '<p>Kevin turns data into insights, helping us make informed decisions for our clients.</p>',
            ],
            [
                'name' => 'Laura Purple',
                'position' => 'Customer Success Manager',
                'bio' => '<p>Laura ensures our clients are happy and successful, building lasting relationships.</p>',
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
                'bio' => '<p>Zane conducts research to develop innovative solutions that push the boundaries of technology.</p>',
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

            $this->translationsFor($content)->createMany(
                $languages
                    ->reject(fn (Language $language): bool => $content->translations->contains('language_id', $language->id))
                    ->map(fn (Language $language): array => [
                        'language_id' => $language->id,
                        'title' => $member['name'],
                        'content' => $member['bio'],
                    ])
                    ->all(),
            );

            $teamMembersCollection->push($content);
        }

        return $teamMembersCollection;
    }

    protected function createBlockMedia(Block $model, ?string $name = null, string $type = 'image', BackedEnum|string $collection = MediaCollectionEnum::Image): Media
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

        // Attach primary media
        $image = null;
        if (! $isVideo) {
            try {
                $image = Image::load($demoFile);
            } catch (Throwable) {
                $image = null;
            }
        }

        // Create content and link via BlockAsset
        $content = $this->contentModel::query()->create([
            'name' => str($filenameBase)->title(),
        ]);
        assert($content instanceof HasMedia);

        $model->assets()->create([
            'asset_id' => $content->getKey(),
            'asset_type' => resolve($this->contentModel)->getMorphClass(),
        ]);

        $media = $content->addMedia($demoFile)
            ->preservingOriginal()
            ->withCustomProperties([
                ...($image instanceof Image ? ['width' => $image->getWidth(), 'height' => $image->getHeight()] : []),
            ])
            ->toMediaCollection($collection instanceof BackedEnum ? $collection->value : $collection);

        // For videos, also attach a jpg poster image
        if (! $isVideo) {
            return $media;
        }

        $posterPath = static::getDemoResourcePath('img');
        $posterBase = $this->getRandomDemoImage($posterPath);
        $posterFile = sprintf('%s/%s.jpg', $posterPath, $posterBase);

        if (! File::exists($posterFile)) {
            return $media;
        }

        try {
            $posterImage = Image::load($posterFile);
        } catch (Throwable) {
            $posterImage = null;
        }

        return $content->addMedia($posterFile)
            ->preservingOriginal()
            ->withCustomProperties([
                ...($posterImage instanceof Image ? [
                    'width' => $posterImage->getWidth(),
                    'height' => $posterImage->getHeight(),
                ] : []),
            ])
            ->toMediaCollection(MediaCollectionEnum::Image->value);
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
