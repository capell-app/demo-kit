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

    protected ?Block $demoPageContentBlock = null;

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
        if ($this->demoPageContentBlock instanceof Block) {
            return $this->demoPageContentBlock;
        }

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
        $block->forceFill($attributes);

        if ($block->isDirty()) {
            $block->save();
        }

        return $this->demoPageContentBlock = $block;
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
            'Contact',
            'FAQ',
            'Pricing',
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
            'home, buildings and architecture' => 'Home, Buildings and Architecture',
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
                'Choose the access and support model that fits your team.',
                'Plan cards, support notes, and implementation scoping stay on this route so the homepage can remain compact and focused.',
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
