<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use BackedEnum;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Contracts\PageCreatable;
use Capell\Core\Enums\ContainerWidthEnum;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Enums\MediaConversionEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Media;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Type;
use Capell\Core\Models\Widget;
use Capell\Core\Models\WidgetAsset;
use Capell\Core\Support\Creator\PageCreator;
use Capell\DemoKit\Actions\DummyContentGeneratorAction;
use Capell\DemoKit\Support\DemoContentPool;
use Capell\LayoutBuilder\Enums\ActionLinkEnum;
use Capell\LayoutBuilder\Enums\ContentTypeEnum;
use Capell\LayoutBuilder\Enums\FrontendComponentKeyEnum;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Enums\WidgetComponentEnum;
use Capell\LayoutBuilder\Enums\WidgetTypeEnum;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Capell\LayoutBuilder\Support\Creator\WidgetCreator;
use Capell\Navigation\Models\Navigation;
use Capell\Navigation\Support\Creator\NavigationCreator;
use Error;
use Exception;
use FilesystemIterator;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Spatie\Image\Image;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use SplFileInfo;
use Throwable;
use ZipArchive;

class DemoCreator
{
    use Macroable;

    private const NavigationPackage = 'capell-app/navigation';

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

    /** @var class-string<Type> */
    public string $typeModel;

    /**
     * @var array<string, list<string>>
     */
    private static array $demoImageFilenames = [];

    /** @var class-string<Model> */
    private readonly string $contentModel;

    /** @var class-string<Widget> */
    private readonly string $widgetModel;

    public function __construct(
        protected ?string $url = null,
        protected ?Model $author = null,
    ) {
        if (in_array($this->url, [null, '', '0'], true)) {
            $this->url = config('app.url');
        }

        $this->languageModel = Language::class;
        $this->layoutModel = Layout::class;
        $this->pageModel = Page::class;
        $this->siteModel = Site::class;
        $this->typeModel = Type::class;
        $this->widgetModel = Widget::class;
        $this->contentModel = CapellCore::hasAsset('Section')
            ? CapellCore::getAsset('Section')->model
            : Page::class;
    }

    public static function getDemoResourcePath(?string $folder): string
    {
        return resolve(DemoResourceResolver::class)->resolve($folder);
    }

    /**
     * @param  null|Collection<int, Language>  $languages  = null
     */
    public function setupSite(Site $site, ?Collection $languages = null): void
    {
        $languages ??= $site->languages;
        $title = ctype_digit($site->name[0]) ? $site->name : Str::title($site->name);

        $meta = $site->meta;

        $meta['business_name'] = $title . ' ltd';
        $meta['email'] = config('mail.from.address');
        $meta['phone'] = '0123456789';
        $meta['footer_content'] = 'Footer content here';
        $meta['social_links'] = [
            [
                'type' => 'facebook',
                'url' => 'https://facebook.com',
                'icon' => 'fab-square-facebook',
            ],
            [
                'type' => 'twitter',
                'url' => 'https://twitter.com',
                'icon' => 'fab-square-x-twitter',
            ],
            [
                'type' => 'instagram',
                'url' => 'https://instagram.com',
                'icon' => 'fab-square-instagram',
            ],
        ];

        $site->update(['meta' => $meta]);

        foreach ($languages as $language) {
            $site->translations()->updateOrCreate(['language_id' => $language->id], [
                'title' => $title,
                'meta' => [
                    'description' => 'Description for ' . $title,
                    'footer_copy' => sprintf('<p>&copy; :year %s</p>', $title),
                ],
            ]);

            $path = '';
            if (! $language->default) {
                $path .= '/' . $language->code;
            }

            if (! $site->default) {
                $path .= '/' . Str::slug($site->name);
            }

            $site->siteDomains()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'domain' => null,
                'scheme' => null,
                'path' => $path !== '' && $path !== '0' ? $path : null,
                'default' => $site->siteDomains()->doesntExist(),
            ]);
        }
    }

    public function createDefaultLanguages(?array $languages = null): void
    {
        foreach (resolve(DemoContentPool::class)->languages() as $item) {
            if (is_array($languages) && ! in_array($item['code'], $languages, true)) {
                continue;
            }

            $language = $this->languageModel::query()->where('code', $item['code'])->first();

            if ($language !== null) {
                $language->update([
                    'name' => $item['name'],
                    'locale' => $item['locale'],
                    'flag' => $item['flag'],
                    'meta' => [
                        'color' => $item['color'],
                    ],
                ]);

                continue;
            }

            $this->languageModel::query()->create([
                'name' => $item['name'],
                'code' => $item['code'],
                'locale' => $item['locale'],
                'flag' => $item['flag'],
                'default' => $this->languageModel::query()->count() === 0,
                'meta' => [
                    'color' => $item['color'],
                ],
            ]);
        }
    }

    /**
     * @param  null|Collection<int, Language>  $languages  =  null
     */
    public function createPage(
        array $data,
        Site $site,
        ?Collection $languages = null,
        ?Page $parent = null,
        ?Type $type = null,
        ?Layout $layout = null,
        bool $createMedia = true,
        ?PageCreatable $pageCreator = null,
    ): Pageable {
        $languages ??= $site->languages;
        $pageCreator ??= new PageCreator;

        $name = Str::title($data['name']['en']);

        $pageData = [
            'name' => $name,
            'user_id' => $this->author?->getKey(),
            'blueprint_id' => $type?->getKey(),
            'layout_id' => $layout?->getKey(),
            'meta' => $this->demoPageMeta($name, $parent),
            'translations' => [],
            'visible_from' => now()->subDays(mt_rand(0, 90))->format('Y-m-d'),
        ];

        if ($parent instanceof Pageable) {
            $pageData['parent_id'] = $parent->getKey();
        }

        $languages->each(function (Language $language) use (&$pageData, $name, $data): void {
            $title = Str::title($data['name'][$language->code]);

            $slug = Str::slug($title);

            $desc_content = $this->demoPageContent($name, $language->code)
                ?? DummyContentGeneratorAction::run($language->code);

            $pageData['translations'][$language->code] = [
                'title' => $title,
                'content' => $desc_content,
                'meta' => [
                    'description' => str($desc_content)->stripTags()->limit(160),
                    'keywords' => implode(',', array_slice(explode(' ', $title), 0, 10)),
                    'label' => Str::title($data['name'][$language->code] ?? $name),
                    'link_text' => $this->randomItem([
                        'Learn More',
                        'Read More',
                        'Get Started',
                        'More information',
                        'Unlock the Full Story',
                    ]),
                    'slug' => $slug,
                ],
            ];
        });

        $page = $pageCreator->createPage($pageData, $site, $languages);

        if ($createMedia) {
            $this->createMedia($page, $name);
        }

        return $page;
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

    public function setupRelatedSites(): void
    {
        $sites = $this->siteModel::with(['language', 'translations'])->get();
        $defaultSite = $this->siteModel::getDefault();

        $this->attachRelatedSites($defaultSite, $sites);

        $sites->each(function (Site $site): void {
            $relatedSites = $this->findRelatedSites($site);

            $site->related()->attach($relatedSites)->save();
        });
    }

    public function createContentWidget(Collection $languages): Widget
    {
        $siteId = Site::query()->default()?->value('id');

        $type = resolve(TypeCreator::class)->contentBuilderWidgetType();

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'example-content'], [
            'name' => 'Example Content',
            'blueprint_id' => $type->id,
            'meta' => [
                'size' => 'md',
                'margin' => 'none',
                'padding' => 'md',
                'reverse_order' => true,
                'background_color' => 'light-gray',
                'actions' => [
                    [
                        'type' => ActionLinkEnum::Page->value,
                        'pageable_type' => resolve(Page::class)->getMorphClass(),
                        'pageable_id' => Page::query()->where('site_id', $siteId)
                            ->whereHas(
                                'type',
                                /** @param Type $query */
                                fn (BuilderContract $query): BuilderContract => $query->listable()->enabled()->accessible(),
                            )
                            ->inRandomOrder()
                            ->value('id'),
                        'site_id' => $siteId,
                    ],
                ],
            ],
        ]);

        $this->createWidgetMedia($widget);

        foreach ($languages as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Example Content',
                    'content' => [
                        [
                            'type' => 'content',
                            'data' => [
                                'content' => DummyContentGeneratorAction::run($language->code),
                            ],
                        ],
                    ],
                ],
            );
        }

        return $widget;
    }

    public function createSplitContentWidget(Collection $languages): Widget
    {
        $siteId = Site::query()->default()?->value('id');

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'example-split-content'], [
            'name' => 'Example Split Content',
            'blueprint_id' => $this->typeModel::query()->firstWhere(['key' => WidgetTypeEnum::SectionBuilder, 'type' => LayoutTypeEnum::Widget])->id,
            'meta' => [
                'align' => 'center',
                'size' => 'md',
                'style' => 'column',
                'padding' => 'xl',
                'margin' => 'none',
                'actions' => [
                    [
                        'type' => ActionLinkEnum::Page->value,
                        'pageable_type' => resolve(Page::class)->getMorphClass(),
                        'pageable_id' => Page::query()->where('site_id', $siteId)
                            ->whereHas(
                                'type',
                                /** @param Type $query */
                                fn (BuilderContract $query): BuilderContract => $query->listable()->enabled()->accessible(),
                            )
                            ->inRandomOrder()
                            ->value('id'),
                        'site_id' => $siteId,
                    ],
                ],
            ],
        ]);

        $this->createWidgetMedia($widget);

        foreach ($languages as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Example Content',
                    'content' => [
                        [
                            'type' => 'content',
                            'data' => [
                                'content' => str(DummyContentGeneratorAction::run($language->code))->limit(200)->toString(),
                            ],
                        ],
                    ],
                ],
            );
        }

        return $widget;
    }

    public function createBannerImageWidget(Collection $languages): Widget
    {
        $widget = resolve(WidgetCreator::class)->bannerImageWidget();

        $media = $this->createWidgetMedia($widget);

        $meta = $widget->meta;

        $meta['background_color'] = 'light-gray';
        $meta['background_image'] = $media->getFullUrl(MediaConversionEnum::Medium->value);

        $widget->meta = $meta;

        foreach ($languages as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Example Banner',
                    'content' => DummyContentGeneratorAction::run($language->code),
                ],
            );
        }

        return $widget;
    }

    public function createGalleryWidget(): Widget
    {
        $widget = resolve(WidgetCreator::class)->galleryWidget();

        if ($widget->assets()->exists()) {
            return $widget;
        }

        for ($i = 1; $i <= 5; $i++) {
            $this->createWidgetMedia($widget);
        }

        return $widget;
    }

    public function createPageCardsWidget(Pageable $page, string $container = 'main', int $occurrence = 1): Widget
    {
        $widget = resolve(WidgetCreator::class)->pagesCardWidget();

        if (
            $widget->assets()
                ->where([
                    'pageable_id' => $page->getKey(),
                    'pageable_type' => $page->getMorphClass(),
                    'container' => $container,
                    'occurrence' => $occurrence,
                ])
                ->exists()
        ) {
            return $widget;
        }

        $relatedPages = $this->pageModel::query()
            ->whereHas('type', fn (BuilderContract $query): BuilderContract => $query->default())
            ->whereHas('image')
            ->where('site_id', $page->site_id)
            ->notHomePage()
            ->inRandomOrder()
            ->limit(3)
            ->get();

        if ($relatedPages->isEmpty()) {
            return $widget;
        }

        $relatedPages->each(fn (Page $relatedPage): WidgetAsset => $widget->assets()->create([
            'pageable_id' => $page->id,
            'pageable_type' => $page->getMorphClass(),
            'asset_id' => $relatedPage->id,
            'asset_type' => resolve($this->pageModel)->getMorphClass(),
            'container' => $container,
            'occurrence' => $occurrence,
        ]));

        return $widget;
    }

    public function createFaqWidget(Collection $languages): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', 'assets');

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'faq'], [
            'key' => 'faq',
            'name' => __('capell-admin::generic.faq'),
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'icon' => 'heroicon-m-question-mark-circle',
                'component' => WidgetComponentEnum::AssetAccordion,
                'margin' => ['lg'],
                'align' => 'center',
            ],
            'admin' => [
                'asset_types' => [
                    'section',
                ],
            ],
        ]);

        foreach ($languages as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => __('capell-layout-builder::heading.faq'),
                    'content' => '<p>You can find answers for commonly asked questions</p>',
                ],
            );
        }

        $contentType = $this->typeModel::query()
            ->where('type', 'section')
            ->where('key', ContentTypeEnum::Builder)
            ->first();

        $parentContent = $this->contentModel::query()->firstOrCreate([
            'name' => 'FAQs',
            'blueprint_id' => $contentType->id,
        ], [
        ]);

        $questions = [
            'en' => [
                'How was this website created?',
                'What is the purpose of this website?',
                'Where did you learn to fly?',
                'When did you become so popular?',
                'Who else helped create this website?',
                'Why did you create this website?',
            ],
            'fr' => [
                'Comment ce site a-t-il été créé?',
                'Quel est le but de ce site?',
                'Où avez-vous appris à voler?',
                'Quand êtes-vous devenu si populaire?',
                'Qui d\'autre a aidé à créer ce site?',
                'Pourquoi avez-vous créé ce site?',
            ],
            'it' => [
                'Come è stato creato questo sito?',
                'Qual è lo scopo di questo sito?',
                'Dove hai imparato a volare?',
                'Quando sei diventato così popolare?',
                'Chi altro ha contribuito a creare questo sito?',
                'Perché hai creato questo sito?',
            ],
            'de' => [
                'Wie wurde diese Website erstellt?',
                'Was ist der Zweck dieser Website?',
                'Wo haben Sie fliegen gelernt?',
                'Wann sind Sie so beliebt geworden?',
                'Wer hat sonst noch bei der Erstellung dieser Website geholfen?',
                'Warum haben Sie diese Website erstellt?',
            ],
            'es' => [
                '¿Cómo se creó este sitio web?',
                '¿Cuál es el propósito de este sitio?',
                '¿Dónde aprendiste a volar?',
                '¿Cuándo te volviste tan popular?',
                '¿Quién más ayudó a crear este sitio?',
                '¿Por qué creaste este sitio?',
            ],
        ];

        foreach ($questions['en'] as $i => $question) {
            $content = $this->contentModel::query()->firstOrCreate([
                'name' => $question,
                'parent_id' => $parentContent->id,
                'blueprint_id' => $contentType->id,
            ]);

            $widget->assets()->firstOrCreate([
                'asset_id' => $content->getKey(),
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);

            foreach ($languages as $language) {
                $desc_content = DummyContentGeneratorAction::run($language->code);

                $content->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    [
                        'title' => Str::title($questions[$language->code][$i]),
                        'content' => [
                            [
                                'type' => 'content',
                                'data' => [
                                    'content' => $desc_content,
                                ],
                            ],
                        ],
                    ],
                );
            }
        }

        return $widget;
    }

    public function createMediaCarouselWidget(): Widget
    {
        $widget = resolve(WidgetCreator::class)->mediaCarouselWidget();

        if ($widget->assets()->exists()) {
            return $widget;
        }

        for ($i = 1; $i <= 7; $i++) {
            $this->createWidgetMedia($widget);
        }

        $this->createWidgetMedia($widget, type: 'video');

        return $widget;
    }

    public function createStaticNavigationWidget(Collection $languages, Site $site): Widget
    {
        $model = Navigation::class;

        // Create menu + items
        $name = 'Example Menu';
        $key = Str::slug($name);

        $pages = Page::query()->where([
            'site_id' => $site->id,
        ])
            ->whereHas(
                'type',
                /** @param  Type  $query */
                fn (BuilderContract $query): BuilderContract => $query->where('type', 'page')
                    ->enabled()
                    ->listable()
                    ->accessible()
                    ->hiddenSystemGroup(),
            )
            ->withWhereHas(
                'children',
                fn (BuilderContract $query): BuilderContract => $query->whereHas('type')->limit(2),
            )
            ->limit(4)
            ->get();

        $widgetType = resolve(TypeCreator::class)->navigationWidgetType();

        $navigationType = $this->typeModel::query()->navigationType()->default()->first();
        if ($navigationType === null) {
            $navigationType = resolve(\Capell\Core\Support\Creator\TypeCreator::class)->createNavigationType();
        }

        $navigation = CapellCore::isPackageInstalled(self::NavigationPackage) && class_exists($model)
            ? $model::query()->updateOrCreate([
                'key' => $key,
                'site_id' => $site->id,
                'blueprint_id' => $navigationType->id,
            ], [
                'name' => $name,
                'items' => $this->navigationPageItems($pages, $languages->first()),
            ])
            : null;

        // Create widget
        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'example-navigation'], [
            'name' => __('Example Navigation'),
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'navigation' => $navigation instanceof Model ? (string) $navigation->getAttribute('key') : $key,
                'margin' => ['lg'],
            ],
        ]);

        foreach ($languages as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Example Navigation',
                ],
            );
        }

        return $widget;
    }

    public function createContentsWidget(Widget $widget, Pageable $page, string $container, int $occurrence = 1, ?Type $type = null): void
    {
        $pageWidgetAssets = $widget->assets()->where([
            'pageable_id' => $page->getKey(),
            'pageable_type' => $page->getMorphClass(),
            'container' => $container,
            'occurrence' => $occurrence,
        ])
            ->exists();

        if ($pageWidgetAssets) {
            return;
        }

        if (! $type instanceof Type) {
            $type = $this->typeModel::query()
                ->where('type', 'section')
                ->default()
                ->first();
        }

        $features = [
            [
                'title' => 'Empower Your Vision',
                'content' => '<p>Step into a world where your ideas become reality. Experience innovation and growth with us.</p>',
            ],
            [
                'title' => 'Start Your Journey',
                'content' => '<p>Begin your adventure today and unlock new opportunities for success.</p>',
            ],
            [
                'title' => 'Explore Our Achievements',
                'content' => '<p>Discover the groundbreaking projects and milestones that define our excellence.</p>',
            ],
            [
                'title' => 'See Our Story Unfold',
                'content' => '<p>Watch our journey and learn how we create impact through passion and expertise.</p>',
            ],
        ];

        foreach ($features as $feature) {
            $content = $this->contentModel::query()->firstOrCreate([
                'name' => $feature['title'],
                'blueprint_id' => $type->getKey(),
            ], [
                'meta' => [
                    'actions' => [
                        [
                            'type' => ActionLinkEnum::Page->value,
                            'pageable_type' => resolve(Page::class)->getMorphClass(),
                            'pageable_id' => Page::query()->where('site_id', $page->site->id)
                                ->whereHas(
                                    'type',
                                    /** @param Type $query */
                                    fn (BuilderContract $query): BuilderContract => $query->listable()->enabled()->accessible(),
                                )
                                ->inRandomOrder()
                                ->value('id'),
                            'site_id' => $page->site->id,
                        ],
                        [
                            'type' => ActionLinkEnum::Page->value,
                            'pageable_type' => resolve(Page::class)->getMorphClass(),
                            'pageable_id' => Page::query()->where('site_id', $page->site->id)
                                ->whereHas(
                                    'type',
                                    /** @param Type $query */
                                    fn (BuilderContract $query): BuilderContract => $query->listable()->enabled()->accessible(),
                                )
                                ->inRandomOrder()
                                ->value('id'),
                            'site_id' => $page->site->id,
                            'color' => 'secondary',
                        ],
                        [
                            'type' => ActionLinkEnum::Link->value,
                            'url' => 'https://example.com',
                            'label' => 'External',
                            'hide_label' => true,
                            'icon' => 'heroicon-o-arrow-top-right-on-square',
                            'color' => 'default',
                        ],
                    ],
                ],
            ]);

            foreach ($page->site->languages as $language) {
                $content->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    [
                        'title' => $feature['title'],
                        'content' => sprintf('<p>%s</p>', $feature['content']),
                    ],
                );
            }

            $this->createMedia($content);

            $widget->assets()->create([
                'pageable_id' => $page->id,
                'pageable_type' => $page->getMorphClass(),
                'container' => $container,
                'occurrence' => $occurrence,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
                'asset_id' => $content->id,
            ]);
        }
    }

    public function createClientLogosWidget(Collection $languages): Widget
    {
        $widget = Widget::query()->firstOrCreate([
            'key' => 'client-logos',
        ], [
            'name' => 'Client Logos',
            'blueprint_id' => $this->typeModel::query()->firstWhere(['key' => WidgetTypeEnum::Assets, 'type' => LayoutTypeEnum::Widget])->id,
            'meta' => [
                'align' => 'center',
                'margin' => ['lg'],
                'columns' => 6,
                'spacing' => 'lg',
                'max_width' => '3xl',
            ],
            'admin' => [
                'icon' => 'heroicon-o-photo',
            ],
        ]);

        if ($widget->assets()->exists()) {
            return $widget;
        }

        $languages->each(function (Language $language) use ($widget): void {
            $widget->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => 'Client Logos',
                'content' => '<p>We are proud to work with these amazing partners.</p>',
            ]);
        });

        for ($i = 1; $i <= 12; $i++) {
            $this->createWidgetMedia($widget);
        }

        return $widget;
    }

    public function createBusinessFeaturesWidget(Site $site): Widget
    {
        $widget = Widget::query()->firstOrCreate([
            'key' => 'business-features',
        ], [
            'name' => 'Business Features',
            'blueprint_id' => $this->typeModel::query()->firstWhere(['key' => WidgetTypeEnum::Sections, 'type' => LayoutTypeEnum::Widget])->id,
            'meta' => [
                'align' => 'center',
                'margin' => ['lg'],
                'view_file' => 'capell-layout-builder::components.widget.asset.features',
            ],
        ]);

        $this->createMedia($widget);

        $title = 'Fundamental Capabilities That Set Us Apart';
        $content = '<p>We combine innovation, efficiency, and deep expertise to deliver exceptional results. Our adaptable, client-focused approach ensures measurable value and lasting impact.</p>';

        $site->languages->each(function (Language $language) use ($widget, $title, $content): void {
            $widget->translations()->updateOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => $title,
                'content' => $content,
            ]);
        });

        $features = $this->createFeatures($site);

        $features->each(function (Model $content) use ($widget): void {
            if ($widget->assets()->where('asset_id', $content->getKey())->exists()) {
                return;
            }

            $widget->assets()->create([
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
                'asset_id' => $content->getKey(),
            ]);
        });

        return $widget;
    }

    public function createBannersWidget(): Widget
    {
        $creator = resolve(WidgetCreator::class);
        $widget = $creator->bannerWidget();

        $site = Site::getDefault();

        $features = $this->createFeatures($site);

        $features->each(function (Model $content) use ($widget): void {
            if ($widget->assets()->where('asset_id', $content->getKey())->exists()) {
                return;
            }

            $widget->assets()->create([
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
                'asset_id' => $content->getKey(),
            ]);
        });

        return $widget;
    }

    public function createTestimonialsWidget(Collection $languages): Widget
    {
        $widgetCreator = resolve(WidgetCreator::class);
        $widget = $widgetCreator->testimonialsWidget();

        $this->createMedia($widget, collection: MediaCollectionEnum::BackgroundImage);

        $languages->each(function (Language $language) use ($widget): void {
            $widget->translations()->firstOrCreate(['language_id' => $language->id], [
                'title' => 'What Our Clients Say',
            ]);
        });

        $testimonials = $this->createTestimonials($languages);

        $testimonials->each(function (Model $content) use ($widget): void {
            if ($widget->assets()->where('asset_id', $content->getKey())->exists()) {
                return;
            }

            $widget->assets()->create([
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
                'asset_id' => $content->getKey(),
            ]);
        });

        return $widget;
    }

    public function createStatisticsWidget(): Widget
    {
        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'statistics'], [
            'name' => 'Statistic Blocks',
            'blueprint_id' => $this->typeModel::query()->firstWhere(['key' => WidgetTypeEnum::Assets, 'type' => LayoutTypeEnum::Widget])->id,
            'meta' => [
                'component_item' => FrontendComponentKeyEnum::SectionBlock->value,
                'view_file' => 'capell-layout-builder::components.widget.asset.blocks',
                'spacing' => 'none',
                'columns' => 4,
                'margin' => 'none',
                'container' => ContainerWidthEnum::Small->value,
            ],
            'admin' => [
                'icon' => 'heroicon-o-chart-bar',
            ],
        ]);

        if ($widget->assets()->exists()) {
            return $widget;
        }

        $statistics = [
            [
                'icon' => 'heroicon-o-users',
                'title' => 'Users',
                'value' => '<p><b>1,200</b></p>',
                'color' => 'primary',
            ],
            [
                'icon' => 'heroicon-o-chart-bar',
                'title' => 'Revenue Increases',
                'value' => '<p><b>300%</b></p>',
                'color' => 'success',
            ],
            [
                'icon' => 'heroicon-o-globe-alt',
                'title' => 'Countries Reached',
                'value' => '<p><b>50+</b></p>',
                'color' => 'info',
            ],
            [
                'icon' => 'heroicon-o-clock',
                'title' => 'Hours Worked',
                'value' => '<p><b>10,000+</b></p>',
                'color' => 'secondary',
            ],
        ];

        $site = Site::getDefault();

        foreach ($statistics as $statistic) {
            $content = $this->contentModel::query()->firstOrCreate([
                'name' => $statistic['title'],
            ], [
                'meta' => [
                    'icon' => $statistic['icon'],
                    'color' => $statistic['color'],
                ],
            ]);

            foreach ($site->languages as $language) {
                $content->translations()->create([
                    'language_id' => $language->id,
                    'title' => $statistic['title'],
                    'content' => sprintf('<p>%s</p>', $statistic['value']),
                ]);
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $content->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createTeamPortfolioWidget(Collection $languages): Widget
    {
        $type = $this->typeModel::query()
            ->where([
                'key' => WidgetTypeEnum::Sections,
                'type' => LayoutTypeEnum::Widget,
            ])
            ->first();

        if ($type === null) {
            $type = resolve(TypeCreator::class)->contentsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'team-portfolio'], [
            'name' => 'Team Portfolio',
            'blueprint_id' => $type->id,
            'meta' => [
                'align' => 'center',
                'padding' => ['lg'],
                'columns' => 4,
                'spacing' => 'lg',
                'background_color' => 'light-gray',
                'with_summary' => true,
                'carousel_fade' => true,
                'carousel_arrows' => false,
                'carousel_pagination' => true,
                'carousel_loop' => true,
                'carousel_auto_play' => true,
                'carousel_auto_delay' => 50000,
                'component_item' => FrontendComponentKeyEnum::SectionTeamMember->value,
            ],
        ]);

        $languages->each(function (Language $language) use ($widget): void {
            $widget->translations()->firstOrCreate(['language_id' => $language->id], [
                'title' => 'Meet Our Team',
                'content' => '<p>Discover the talented individuals behind our success.</p>',
            ]);
        });

        $teamMembers = $this->createTeamMembers($languages);

        $teamMembers->each(function (Model $content) use ($widget): void {
            if ($widget->assets()->where('asset_id', $content->getKey())->exists()) {
                return;
            }

            $widget->assets()->create([
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
                'asset_id' => $content->getKey(),
            ]);
        });

        return $widget;
    }

    public function createModernFeatureListWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'modern-feature-list'], [
            'name' => 'Modern Feature List',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApFeatureList,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Built for teams who need CMS control and engineering discipline',
                    'content' => '<p>Capell keeps the public frontend fast while giving editors, developers, and release owners clear ownership of the same content surface.</p>',
                ],
            );
        }

        $widget->assets()->delete();

        $features = [
            ['icon' => 'heroicon-o-rocket-launch', 'title' => 'Static-first public pages', 'description' => 'Serve generated HTML and keep render-time cache work from making the frontend feel brittle.'],
            ['icon' => 'heroicon-o-lock-closed', 'title' => 'Admin-safe editing', 'description' => 'Filament resources control the content without exposing authoring metadata in public output.'],
            ['icon' => 'heroicon-o-globe-alt', 'title' => 'Multi-site and multi-language', 'description' => 'One install can support multiple domains, trees, languages, and layouts.'],
            ['icon' => 'heroicon-o-puzzle-piece', 'title' => 'Package-owned runtime', 'description' => 'Every package owns the frontend assets it needs and doctor verifies those builds exist.'],
            ['icon' => 'heroicon-o-code-bracket-square', 'title' => 'Laravel-native extension points', 'description' => 'Actions, DTOs, render hooks, schema extenders, and package manifests keep integrations maintainable.'],
            ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Install health reporting', 'description' => 'A fresh demo ends with explicit checks for homepage, widgets, assets, users, and generated CSS.'],
        ];

        foreach ($features as $feature) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $feature['title']], [
                'meta' => ['icon' => $feature['icon']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $feature['title'], 'content' => sprintf('<p>%s</p>', $feature['description'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createModernTeamMembersWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'modern-team-members'], [
            'name' => 'Modern Team Members',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApTeamMembers,
                'columns' => 3,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['title' => 'Our Team'],
            );
        }

        if ($widget->assets()->exists()) {
            return $widget;
        }

        $members = [
            [
                'icon' => '👩‍💼',
                'name' => 'Alex Morgan',
                'position' => 'Product Lead',
                'bio' => 'Creative designer with 5+ years building user-centred digital products.',
                'tags' => ['Design', 'Leadership'],
                'social' => ['twitter' => 'https://twitter.com', 'linkedin' => 'https://linkedin.com'],
            ],
            [
                'icon' => '👨‍🔬',
                'name' => 'Emma Davis',
                'position' => 'Engineering Manager',
                'bio' => 'Full-stack developer and systems architect with a passion for clean APIs.',
                'tags' => ['Engineering', 'Architecture'],
                'social' => ['github' => 'https://github.com', 'linkedin' => 'https://linkedin.com'],
            ],
            [
                'icon' => '🧑‍💼',
                'name' => 'James Wilson',
                'position' => 'CEO & Co-founder',
                'bio' => 'Serial entrepreneur and technology visionary driving our strategic direction.',
                'tags' => ['Strategy', 'Leadership'],
                'social' => ['twitter' => 'https://twitter.com', 'linkedin' => 'https://linkedin.com'],
            ],
        ];

        foreach ($members as $member) {
            $section = $this->contentModel::query()->firstOrCreate(['name' => $member['name']], [
                'meta' => [
                    'icon' => $member['icon'],
                    'position' => $member['position'],
                    'tags' => $member['tags'],
                    'social' => $member['social'],
                ],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $member['name'], 'content' => sprintf('<p>%s</p>', $member['bio'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createModernPricingTableWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'modern-pricing-table'], [
            'name' => 'Modern Pricing Table',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApPricingTable,
                'currency' => '$',
                'billing_options' => 'both',
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['title' => 'Simple, Transparent Pricing'],
            );
        }

        if ($widget->assets()->exists()) {
            return $widget;
        }

        $plans = [
            [
                'name' => 'Starter',
                'description' => 'For individuals and small projects',
                'price' => '29',
                'price_annual' => '290',
                'featured' => false,
                'cta_label' => 'Get Started',
                'cta_url' => '#',
                'features' => ['Up to 5 pages', '1 site', 'Email support', 'Basic widgets'],
            ],
            [
                'name' => 'Professional',
                'description' => 'For growing teams and businesses',
                'price' => '79',
                'price_annual' => '790',
                'featured' => true,
                'cta_label' => 'Start Free Trial',
                'cta_url' => '#',
                'features' => ['Unlimited pages', '5 sites', 'Priority support', 'All widgets', 'Multi-language'],
            ],
            [
                'name' => 'Enterprise',
                'description' => 'For large-scale deployments',
                'price' => 'Custom',
                'price_annual' => 'Custom',
                'featured' => false,
                'cta_label' => 'Contact Sales',
                'cta_url' => '#',
                'features' => ['Unlimited everything', 'Dedicated support', 'Custom integrations', 'SLA guarantee'],
            ],
        ];

        foreach ($plans as $plan) {
            $section = $this->contentModel::query()->firstOrCreate(['name' => $plan['name']], [
                'meta' => [
                    'price' => $plan['price'],
                    'price_annual' => $plan['price_annual'],
                    'featured' => $plan['featured'],
                    'cta_label' => $plan['cta_label'],
                    'cta_url' => $plan['cta_url'],
                    'features' => $plan['features'],
                ],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $plan['name'], 'content' => sprintf('<p>%s</p>', $plan['description'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createModernTestimonialsWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'modern-testimonials'], [
            'name' => 'Modern Testimonials',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApTestimonials,
                'columns' => 2,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'What a release-ready Capell site should prove',
                    'content' => '<p>The default demo should make the CMS story obvious from the first load: editable content, fast frontend, package runtime, and admin traceability.</p>',
                ],
            );
        }

        $widget->assets()->delete();

        $testimonials = [
            ['icon' => 'heroicon-o-user-circle', 'author' => 'Content editor', 'position' => 'Homepage owner', 'quote' => 'I can change the hero, cards, media, and CTA from admin records without waiting on a template deployment.'],
            ['icon' => 'heroicon-o-command-line', 'author' => 'Laravel developer', 'position' => 'Package builder', 'quote' => 'The package boundaries are clear: runtime assets, schema, render hooks, and demo fixtures stay with the package that owns them.'],
            ['icon' => 'heroicon-o-shield-check', 'author' => 'Release lead', 'position' => 'Install verifier', 'quote' => 'The installer tells me whether the homepage, assets, demo content, and frontend CSS are ready before I hand the site over.'],
        ];

        foreach ($testimonials as $testimonial) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $testimonial['author']], [
                'meta' => [
                    'icon' => $testimonial['icon'],
                    'position' => $testimonial['position'],
                ],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $testimonial['author'], 'content' => sprintf('<p>%s</p>', $testimonial['quote'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createModernFaqWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'modern-faq'], [
            'name' => 'Modern FAQ Section',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApFaqSection,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Questions this demo answers',
                    'content' => '<p>These are the checks a serious CMS demo needs to make obvious before release.</p>',
                ],
            );
        }

        $widget->assets()->delete();

        $faqs = [
            ['category' => 'Editing', 'question' => 'Can every visible homepage section be edited in admin?', 'answer' => 'Yes. The hero, cards, feature list, gallery, testimonials, FAQ, and CTA are backed by widget translations, widget meta, assets, and media records.'],
            ['category' => 'Frontend', 'question' => 'Does the public theme own its runtime styling and JavaScript?', 'answer' => 'Yes. Foundation registers and publishes its own frontend build assets instead of relying on another package runtime.'],
            ['category' => 'Install', 'question' => 'How do I know the demo installed correctly?', 'answer' => 'Run capell:doctor --install-summary. It checks tables, packages, homepage data, widgets, runtime assets, generated CSS, and admin access.'],
            ['category' => 'Architecture', 'question' => 'Is this just a landing page?', 'answer' => 'No. The default demo is a working CMS surface that demonstrates Capell page records, layout containers, widgets, media, and package renderers.'],
        ];

        foreach ($faqs as $faq) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $faq['question']], [
                'meta' => ['category' => $faq['category']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $faq['question'], 'content' => sprintf('<p>%s</p>', $faq['answer'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createModernStatsSectionWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'modern-stats'], [
            'name' => 'Modern Stats Section',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApStatsSection,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Proof points for a healthier release',
                    'content' => '<p>The default demo now checks the signals that matter before a Capell site is handed over.</p>',
                ],
            );
        }

        $widget->assets()->delete();

        $stats = [
            ['icon' => 'heroicon-o-squares-2x2', 'label' => 'Homepage widgets', 'value' => '10'],
            ['icon' => 'heroicon-o-photo', 'label' => 'Demo media records', 'value' => '8+'],
            ['icon' => 'heroicon-o-bolt', 'label' => 'Runtime asset checks', 'value' => '2'],
            ['icon' => 'heroicon-o-check-badge', 'label' => 'Doctor summary', 'value' => 'Pass'],
        ];

        foreach ($stats as $stat) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $stat['label']], [
                'meta' => ['icon' => $stat['icon']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $stat['label'], 'content' => sprintf('<p>%s</p>', $stat['value'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createModernAlternatingContentWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'modern-alternating-content'], [
            'name' => 'Modern Alternating Content',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApAlternatingContent,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'From model to public page',
                    'content' => '<p>Capell keeps the frontend impressive because every layer has an owner and a verification path.</p>',
                ],
            );
        }

        $widget->assets()->delete();

        $steps = [
            ['icon' => 'heroicon-o-circle-stack', 'position' => 'left', 'title' => 'Model the content', 'description' => 'Define page types, widgets, translations, and media so content stays structured instead of trapped in templates.'],
            ['icon' => 'heroicon-o-rectangle-group', 'position' => 'right', 'title' => 'Compose the layout', 'description' => 'Place package-owned widgets into layout containers and keep every visible section editable from the admin.'],
            ['icon' => 'heroicon-o-paper-airplane', 'position' => 'left', 'title' => 'Publish and verify', 'description' => 'Generate frontend resources, warm static output, and let doctor report missing homepage, asset, or fixture problems.'],
        ];

        foreach ($steps as $step) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $step['title']], [
                'meta' => ['icon' => $step['icon'], 'position' => $step['position']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $step['title'], 'content' => sprintf('<p>%s</p>', $step['description'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createModernProcessStepsWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'modern-process-steps'], [
            'name' => 'Modern Process Steps',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApProcessSteps,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'The publishing path Capell demonstrates',
                    'content' => '<p>The demo homepage should show a real CMS workflow, not a pile of disconnected sample widgets.</p>',
                ],
            );
        }

        $widget->assets()->delete();

        $steps = [
            ['icon' => 'heroicon-o-cog-6-tooth', 'title' => 'Install packages', 'description' => 'Core, frontend, Foundation theme, navigation, search, and content packages register their own setup and runtime surfaces.'],
            ['icon' => 'heroicon-o-swatch', 'title' => 'Seed the showcase', 'description' => 'Demo fixtures create Capell-specific widgets, sections, media, and translations in the right homepage order.'],
            ['icon' => 'heroicon-o-arrow-path', 'title' => 'Rebuild resources', 'description' => 'Tailwind input, published runtime manifests, and static frontend resources are generated after package demo steps.'],
            ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Run doctor', 'description' => 'The installer ends with a health summary that catches broken homepage, runtime, and fixture states immediately.'],
        ];

        foreach ($steps as $step) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $step['title']], [
                'meta' => ['icon' => $step['icon']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $step['title'], 'content' => sprintf('<p>%s</p>', $step['description'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createModernImageGalleryWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsWidgetType();
        }

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'modern-image-gallery'], [
            'name' => 'Modern Image Gallery',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApImageGallery,
                'columns' => 3,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'A curated media surface, still CMS-owned',
                    'content' => '<p>The gallery proves that images are not just decorative assets in the theme. They are media records that can be replaced, reordered, and rendered consistently.</p>',
                ],
            );
        }

        if ($widget->assets()->exists()) {
            return $widget;
        }

        for ($i = 1; $i <= 6; $i++) {
            $this->createWidgetMedia($widget);
        }

        return $widget;
    }

    public function createHomepageHeroCommandCenterWidget(): Widget
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-hero-command-center',
            name: 'Capell Homepage Command Center Hero',
            content: $this->homepageHeroCommandCenterHtml(),
        );
    }

    public function createHomepageProofStripWidget(): Widget
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-proof-strip',
            name: 'Capell Homepage Proof Strip',
            content: $this->homepageProofStripHtml(),
        );
    }

    public function createHomepageDemoShowcaseWidget(): Widget
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-demo-showcase',
            name: 'Capell Homepage Demo Showcase',
            content: $this->homepageDemoShowcaseHtml(),
        );
    }

    public function createHomepageMarketplaceWidget(): Widget
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-extension-marketplace-showcase',
            name: 'Extension Marketplace Showcase',
            content: $this->homepageMarketplaceHtml(),
        );
    }

    public function createHomepageTechnicalPipelineWidget(): Widget
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-technical-pipeline',
            name: 'Capell Homepage Technical Pipeline',
            content: $this->homepageTechnicalPipelineHtml(),
        );
    }

    public function createHomepageRouteSplitWidget(): Widget
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-route-split',
            name: 'Capell Homepage Route Split',
            content: $this->homepageRouteSplitHtml(),
        );
    }

    public function createHomepageFinalCtaWidget(): Widget
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-final-cta',
            name: 'Capell Homepage Final CTA',
            content: $this->homepageFinalCtaHtml(),
        );
    }

    public function createApHeroBannerWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::HeroBanner)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
                ->firstWhere('key', WidgetTypeEnum::Default);

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'ap-hero-banner'], [
            'name' => 'AP Hero Banner',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApHeroBanner,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Product Hero',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApHeroBanner,
                'primary_button_text' => 'Explore the demo',
                'primary_button_url' => '/admin',
                'secondary_button_text' => 'Read the docs',
                'secondary_button_url' => '/docs/installation',
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Capell CMS',
                    'content' => '<p>The Laravel and Filament CMS operating system for multi-site publishing, visual layout building, package-owned frontends, and static-fast delivery.</p>',
                ],
            );
        }

        $this->createMedia($widget, 'sharks', collection: MediaCollectionEnum::BackgroundImage);

        return $widget;
    }

    public function createApCardGridWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::CardGrid)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
                ->firstWhere('key', WidgetTypeEnum::Default);

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'ap-card-grid'], [
            'name' => 'Capell Capability Cards',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApCardGrid,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Capability Cards',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApCardGrid,
                'columns' => 3,
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'A complete CMS foundation, not a theme demo',
                    'content' => '<p>Capell brings the content model, admin workflow, frontend runtime, and release checks together so teams can ship production sites without stitching every layer by hand.</p>',
                ],
            );
        }

        $widget->assets()->delete();

        $cards = [
            ['icon' => 'heroicon-o-circle-stack', 'title' => 'Structured content engine', 'description' => 'Model pages, sections, widgets, media, translations, and relationships with clear Laravel records instead of hardcoded templates.', 'link_text' => 'Inspect the model', 'link_url' => '/admin'],
            ['icon' => 'heroicon-o-rectangle-group', 'title' => 'Visual layout builder', 'description' => 'Compose real frontend sections from editable widgets while keeping rendering package-owned and predictable.', 'link_text' => 'Edit the homepage', 'link_url' => '/admin'],
            ['icon' => 'heroicon-o-bolt', 'title' => 'Static-fast delivery', 'description' => 'Generate frontend HTML, verify runtime assets, and keep public pages fast without giving up CMS control.', 'link_text' => 'Run doctor', 'link_url' => '/docs/installation'],
        ];

        foreach ($cards as $card) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $card['title']], [
                'meta' => [
                    'icon' => $card['icon'],
                    'link_text' => $card['link_text'],
                    'link_url' => $card['link_url'],
                ],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $card['title'], 'content' => sprintf('<p>%s</p>', $card['description'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createApFeatureListWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::FeatureList)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
                ->firstWhere('key', WidgetTypeEnum::Default);

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'ap-feature-list'], [
            'name' => 'Capell Workflow Feature List',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApFeatureList,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Workflow Feature List',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApFeatureList,
                'layout' => 'grid',
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Everything visible is backed by editable records',
                    'content' => '<p>The default homepage is deliberately assembled from Capell widgets, assets, media, and translations so the admin experience proves the frontend is not a static mockup.</p>',
                ],
            );
        }

        $widget->assets()->delete();

        $features = [
            ['icon' => 'heroicon-o-language', 'title' => 'Page translations', 'description' => 'Hero titles, body copy, SEO fields, and language variants live in translation records.'],
            ['icon' => 'heroicon-o-photo', 'title' => 'Media-driven surfaces', 'description' => 'Hero backgrounds, gallery items, cards, and section imagery resolve through Capell media records.'],
            ['icon' => 'heroicon-o-pencil-square', 'title' => 'Editor-owned sections', 'description' => 'Homepage cards, feature rows, FAQs, testimonials, and CTAs are all admin-managed content.'],
            ['icon' => 'heroicon-o-shield-check', 'title' => 'Release diagnostics', 'description' => 'Doctor checks verify the demo, homepage, widgets, runtime manifests, and generated frontend CSS.'],
        ];

        foreach ($features as $feature) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $feature['title']], [
                'meta' => ['icon' => $feature['icon']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $feature['title'], 'content' => sprintf('<p>%s</p>', $feature['description'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createFeatureListWidget(): Widget
    {
        $widget = resolve(WidgetCreator::class)->featuresWidget();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->firstOrCreate(
                ['language_id' => $language->id],
                ['title' => 'Features'],
            );
        }

        if ($widget->assets()->exists()) {
            return $widget;
        }

        $features = [
            ['icon' => 'heroicon-o-light-bulb', 'title' => 'Innovative Solutions', 'description' => 'We leverage cutting-edge technology to create innovative solutions that drive success.'],
            ['icon' => 'heroicon-o-academic-cap', 'title' => 'Deep Expertise', 'description' => 'Our team brings deep industry knowledge and experience to every project.'],
            ['icon' => 'heroicon-o-user-group', 'title' => 'Client-Centric Approach', 'description' => "We prioritize our clients' needs and work collaboratively to achieve their goals."],
            ['icon' => 'heroicon-o-chart-bar', 'title' => 'Measurable Results', 'description' => 'We focus on delivering measurable results that drive growth and success.'],
            ['icon' => 'heroicon-o-sparkles', 'title' => 'Sustainable Practices', 'description' => 'We are committed to sustainable practices that benefit our clients and the environment.'],
            ['icon' => 'heroicon-o-globe-alt', 'title' => 'Global Reach', 'description' => 'Our global presence allows us to serve clients across diverse markets and industries.'],
        ];

        foreach ($features as $feature) {
            $section = $this->contentModel::query()->firstOrCreate(['name' => $feature['title']], [
                'meta' => ['icon' => $feature['icon']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $section->translations()->firstOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $feature['title'], 'content' => sprintf('<p>%s</p>', $feature['description'])],
                );
            }

            $widget->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $widget;
    }

    public function createApCtaSectionWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::CTASection)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
                ->firstWhere('key', WidgetTypeEnum::Default);

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'ap-cta-section'], [
            'name' => 'AP CTA Section',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApCTASection,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Showcase CTA',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApCTASection,
                'primary_button_text' => 'Open the admin',
                'primary_button_url' => '/admin',
                'secondary_button_text' => 'Run install doctor',
                'secondary_button_url' => '/docs/installation',
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'A demo site that proves the CMS stack is wired',
                    'content' => '<p>Change the homepage in Filament, regenerate the frontend, and use Capell doctor to confirm content, assets, runtime JavaScript, and layouts are all healthy.</p>',
                ],
            );
        }

        return $widget;
    }

    public function createApImageGalleryWidget(): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::ImageGallery)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
                ->firstWhere('key', WidgetTypeEnum::Default);

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => 'ap-image-gallery'], [
            'name' => 'AP Image Gallery',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApImageGallery,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Media Gallery',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::ApImageGallery,
                'layout' => 'grid',
                'columns' => 3,
                'lightbox' => true,
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Media that stays editable',
                    'content' => '<p>Use the gallery to verify image records, captions, crops, and frontend rendering stay connected from admin to public page.</p>',
                ],
            );
        }

        if ($widget->assets()->exists()) {
            return $widget;
        }

        for ($i = 1; $i <= 6; $i++) {
            $this->createWidgetMedia($widget);
        }

        return $widget;
    }

    public function addSplitTwoBackgroundMedia(Layout $layout): void
    {
        if ($layout->getMedia('split-two-background')->isNotEmpty()) {
            return;
        }

        $this->createMedia($layout, collection: 'split-two-background');
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
            return (string) $navigationCreator::getPageNavigationLabel($page, $language);
        }

        return $page->translation?->title ?? $page->name;
    }

    private static function ensureStorageDemoResources(): string
    {
        return resolve(DemoResourceResolver::class)->ensureStorageDemoResources();
    }

    private static function assertSafeDemoZipEntries(ZipArchive $zip): void
    {
        resolve(DemoResourceResolver::class)->assertSafeDemoZipEntries($zip);
    }

    private function createHomepageSnippetWidget(string $key, string $name, string $content): Widget
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Widget)
            ->firstWhere('key', WidgetTypeEnum::Default);

        $widgetType ??= $this->typeModel::query()
            ->where('type', LayoutTypeEnum::Widget->value)
            ->firstWhere('key', WidgetTypeEnum::Default->value);

        throw_unless($widgetType instanceof Type, Exception::class, 'Unable to find default widget type.');

        $widget = $this->widgetModel::query()->firstOrCreate(['key' => $key], [
            'name' => $name,
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::Snippet,
                'heading_size' => 'h2',
                'content_divider' => false,
                'margin' => ['none'],
            ],
        ]);

        $widget->forceFill([
            'name' => $name,
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => WidgetComponentEnum::Snippet,
                'heading_size' => 'h2',
                'content_divider' => false,
                'margin' => ['none'],
            ],
        ])->save();

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $widget->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['title' => null, 'content' => $content],
            );
        }

        return $widget;
    }

    private function homepageHeroCommandCenterHtml(): string
    {
        return <<<'HTML'
<div class="capell-home capell-home-hero">
    <section class="capell-home-hero__copy">
        <p class="capell-home-kicker">Capell CMS</p>
        <h1>Composable content infrastructure for Laravel teams</h1>
        <p>Ship multi-site CMS platforms without template sprawl: typed content, editor-owned layouts, package-owned rendering, static output, and diagnostics in one Laravel-native system.</p>
        <div class="capell-home-actions">
            <a class="capell-home-button" href="/resources">Explore the demo</a>
            <a class="capell-home-button capell-home-button--secondary" href="/pricing/implementation">View implementation path</a>
        </div>
    </section>
    <section class="capell-home-command-board" aria-label="Capell system board">
        <div class="capell-home-board-row is-active"><span>Page types</span><strong>Home, Resources, Services</strong><em>Typed</em></div>
        <div class="capell-home-board-row"><span>Packages</span><strong>Layout Builder, SEO, Search, Publishing</strong><em>Installed</em></div>
        <div class="capell-home-board-row"><span>Workflow</span><strong>Draft, preview, approve, publish</strong><em>Traceable</em></div>
        <div class="capell-home-board-row"><span>Frontend</span><strong>Static HTML, Vite assets, cache checks</strong><em>Ready</em></div>
        <div class="capell-home-board-footer">
            <div><strong>12+</strong><span>package surfaces</span></div>
            <div><strong>4</strong><span>release checks</span></div>
            <div><strong>0</strong><span>template leaks</span></div>
        </div>
    </section>
</div>
HTML;
    }

    private function homepageProofStripHtml(): string
    {
        return <<<'HTML'
<div class="capell-home capell-home-proof-strip" aria-label="Demo proof points">
    <div><strong>38</strong><span>packages installed</span></div>
    <div><strong>7</strong><span>custom homepage widgets</span></div>
    <div><strong>120+</strong><span>static pages generated</span></div>
    <div><strong>4</strong><span>discovery checks</span></div>
</div>
HTML;
    }

    private function homepageDemoShowcaseHtml(): string
    {
        return <<<'HTML'
<div class="capell-home capell-home-showcase">
    <div class="capell-home-section-head">
        <p class="capell-home-kicker">What ships in the demo</p>
        <h2>Custom layouts that prove the CMS can change shape</h2>
        <p>Each homepage region uses a different composition so the demo feels like a real system, not a repeated stack of generic cards.</p>
    </div>
    <div class="capell-home-showcase-grid">
        <article class="capell-home-console-panel">
            <div>
                <p class="capell-home-kicker">Editorial command center</p>
                <h3>Operational content, not placeholder blocks</h3>
                <p>Use widget translations, page types, layout containers, and package data to show how an editor-owned surface stays structured.</p>
            </div>
            <dl>
                <div><dt>Owner</dt><dd>Publishing Studio</dd></div>
                <div><dt>Surface</dt><dd>Homepage + public pages</dd></div>
                <div><dt>Status</dt><dd>Editable</dd></div>
            </dl>
        </article>
        <article class="capell-home-market-grid-preview">
            <p class="capell-home-kicker">Package marketplace</p>
            <h3>Extension evidence grid</h3>
            <div>
                <span>SEO Suite</span>
                <span>Search</span>
                <span>Forms</span>
                <span>Access Gate</span>
                <span>Newsletter</span>
                <span>Insights</span>
            </div>
        </article>
        <article class="capell-home-workflow-panel">
            <p class="capell-home-kicker">Publishing workflow</p>
            <h3>Timeline plus checklist</h3>
            <ol>
                <li><strong>Model</strong><span>Types and widgets</span></li>
                <li><strong>Compose</strong><span>Layout containers</span></li>
                <li><strong>Release</strong><span>Cache and sitemap</span></li>
            </ol>
        </article>
    </div>
</div>
HTML;
    }

    private function homepageMarketplaceHtml(): string
    {
        return <<<'HTML'
<div class="capell-home capell-home-marketplace capell-extension-marketplace-section">
    <div>
        <p class="capell-home-kicker capell-section-kicker">Marketplace extensions</p>
        <h2>Extension pages that help teams decide</h2>
        <p>Extension detail pages show the contract behind each package: install eligibility, licence state, surfaces, dependencies, frontend budget, health status, documentation, feedback controls, and screenshot galleries.</p>
    </div>
    <div class="capell-home-marketplace-grid capell-extension-marketplace-grid">
        <div><strong>See the product before installing</strong><span>Large screenshots make admin pages, frontend components, settings screens, and workflows visible without leaving Capell.</span></div>
        <div><strong>Keep extension boundaries explicit</strong><span>Surfaces, package dependencies, contribution counts, and performance budgets tell developers what the extension adds.</span></div>
        <div><strong>Connect docs to the buying decision</strong><span>Public and entitled documentation sit beside licence status, access checks, version history, and Marketplace actions.</span></div>
    </div>
</div>
HTML;
    }

    private function homepageTechnicalPipelineHtml(): string
    {
        return <<<'HTML'
<div class="capell-home capell-home-pipeline">
    <div class="capell-home-pipeline__intro">
        <p class="capell-home-kicker">Release path</p>
        <h2>From admin edits to verified frontend</h2>
        <p>Capell keeps the editable CMS surface and the generated public output connected through explicit ownership and checks.</p>
    </div>
    <ol>
        <li><span>01</span><strong>Model content</strong><p>Define typed pages, widgets, translations, media, and package fields.</p></li>
        <li><span>02</span><strong>Compose layout</strong><p>Place widgets into containers that the public theme renders predictably.</p></li>
        <li><span>03</span><strong>Publish safely</strong><p>Preview changes, approve releases, warm cache, and generate static HTML.</p></li>
        <li><span>04</span><strong>Verify output</strong><p>Run doctor, discovery, sitemap, and runtime asset checks before handover.</p></li>
    </ol>
</div>
HTML;
    }

    private function homepageRouteSplitHtml(): string
    {
        return <<<'HTML'
<div class="capell-home capell-home-route-split">
    <a href="/resources">
        <span>Resources hub</span>
        <strong>Technical guides and launch checklists</strong>
        <em>Read the CMS playbook</em>
    </a>
    <a href="/pricing/implementation">
        <span>Implementation pricing</span>
        <strong>A scoped path from install to handover</strong>
        <em>Plan the rollout</em>
    </a>
    <a href="/contact/services">
        <span>Services</span>
        <strong>Architecture, migration, and package support</strong>
        <em>Book scoping</em>
    </a>
</div>
HTML;
    }

    private function homepageFinalCtaHtml(): string
    {
        return <<<'HTML'
<div class="capell-home capell-home-final">
    <div>
        <p class="capell-home-kicker">Demo install</p>
        <h2>Show a CMS that feels assembled, verified, and ready to extend.</h2>
        <p>The homepage now demonstrates multiple layout shapes, custom widget compositions, package boundaries, and public-page discovery paths.</p>
    </div>
    <a class="capell-home-button" href="/contact/services">Start implementation scoping</a>
</div>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private function demoPageMeta(string $name, ?Page $parent): array
    {
        if ($name === 'Contact' && ! $parent instanceof Page) {
            return [
                'robots' => ['noindex'],
                'demo_exclude_reason' => 'Plain contact route is reserved for contact-message flows; service pages remain discoverable.',
            ];
        }

        return [];
    }

    private function demoPageContent(string $name, string $languageCode): ?string
    {
        if ($languageCode !== 'en') {
            return null;
        }

        return match ($name) {
            'Contact' => $this->contactIndexContent(),
            'Services' => $this->contactServicesContent(),
            'Pricing' => $this->pricingIndexContent(),
            'Implementation' => $this->implementationPricingContent(),
            'Resources' => $this->resourcesHubContent(),
            default => null,
        };
    }

    private function contactIndexContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-contact-gateway">
    <section class="capell-demo-gateway">
        <div class="capell-demo-gateway__intro">
            <p class="capell-demo-kicker">Contact</p>
            <h1>Contact routing for CMS teams</h1>
            <p>Send implementation work to the technical intake path and keep the plain contact URL available for a future message flow. This page is deliberately built as a routing gateway, not a generic contact form.</p>
        </div>
        <div class="capell-demo-gateway__routes">
            <article>
                <span>Primary route</span>
                <h2>Implementation enquiries</h2>
                <p>Architecture reviews, migrations, package integration, and launch planning for Laravel teams evaluating Capell.</p>
                <a class="capell-demo-button" href="/contact/services">Open service scoping</a>
            </article>
            <article>
                <span>Reserved route</span>
                <h2>Plain message route</h2>
                <p>The base contact page can stay excluded from discovery while a future contact-message package owns the message workflow.</p>
                <a class="capell-demo-button capell-demo-button--secondary" href="/resources">Read resources first</a>
            </article>
        </div>
        <aside class="capell-demo-route-status">
            <p class="capell-demo-brief__label">Routing status</p>
            <dl>
                <div><dt>Discovery</dt><dd>Noindex base contact URL</dd></div>
                <div><dt>Lead path</dt><dd>/contact/services</dd></div>
                <div><dt>Response window</dt><dd>2 engineering days</dd></div>
            </dl>
        </aside>
    </section>
</div>
HTML;
    }

    private function contactServicesContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-services-atelier">
    <section class="capell-demo-atelier">
        <div class="capell-demo-atelier__copy">
            <p class="capell-demo-kicker">Services</p>
            <h1>Implementation services for complex Capell rollouts</h1>
            <p>Use a technical service desk for content modelling, migration paths, layout architecture, package boundaries, and launch verification before production work starts.</p>
            <div class="capell-demo-actions">
                <a class="capell-demo-button" href="/contact/services#scoping">Book scoping call</a>
                <a class="capell-demo-button capell-demo-button--secondary" href="/pricing/implementation">See implementation pricing</a>
            </div>
        </div>
        <aside class="capell-demo-audit-board" id="scoping">
            <p class="capell-demo-brief__label">Audit board</p>
            <ol>
                <li><span>01</span><strong>Content inventory</strong><em>Types, media, redirects</em></li>
                <li><span>02</span><strong>Editor workflow</strong><em>Roles, drafts, approvals</em></li>
                <li><span>03</span><strong>Frontend ownership</strong><em>Layouts, tokens, cache</em></li>
                <li><span>04</span><strong>Package surface</strong><em>Search, forms, SEO, auth</em></li>
            </ol>
        </aside>
    </section>

    <section class="capell-demo-workbench">
        <div>
            <span>Migration</span>
            <h2>Legacy content imports</h2>
            <p>Convert Blade pages, WordPress exports, spreadsheets, or database tables into typed Capell content with repeatable validation.</p>
        </div>
        <div>
            <span>Theme systems</span>
            <h2>Frontend architecture</h2>
            <p>Build reusable public surfaces without letting admin concerns leak into package-owned rendering.</p>
        </div>
        <div>
            <span>Workflow</span>
            <h2>Editorial operations</h2>
            <p>Define publishing rules, review states, preview paths, and release checks that editors can operate safely.</p>
        </div>
    </section>

    <section class="capell-demo-service-strip">
        <div><strong>2-6 weeks</strong><span>typical implementation window</span></div>
        <div><strong>12+</strong><span>packages checked per rollout</span></div>
        <div><strong>50k+</strong><span>records handled in migration fixtures</span></div>
    </section>
</div>
HTML;
    }

    private function pricingIndexContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-pricing-matrix">
    <section class="capell-demo-pricing-index">
        <div>
            <p class="capell-demo-kicker">Pricing</p>
            <h1>Pricing paths for Capell delivery</h1>
            <p>Choose the path that matches your ownership model: internal implementation, guided launch support, or a productized partner build.</p>
        </div>
        <aside>
            <span>Recommended first step</span>
            <strong>Implementation pricing</strong>
            <p>Use the scoped implementation page when migration, launch risk, or editorial workflow is part of the buying decision.</p>
            <a class="capell-demo-button" href="/pricing/implementation">View implementation pricing</a>
        </aside>
    </section>

    <section class="capell-demo-compare">
        <div class="capell-demo-compare__heading">
            <p class="capell-demo-kicker">Compare paths</p>
            <h2>Start from the work, not a generic tier</h2>
        </div>
        <div class="capell-demo-compare__grid">
            <div><span>Path</span><strong>Internal team</strong><strong>Guided launch</strong><strong>Partner build</strong></div>
            <div><span>Best for</span><p>Laravel team owns delivery</p><p>Team wants guardrails</p><p>First release outsourced</p></div>
            <div><span>Includes</span><p>Documentation and packages</p><p>Architecture, imports, QA</p><p>Full setup and handover</p></div>
            <div><span>Risk level</span><p>Known stack</p><p>Medium migration risk</p><p>High launch pressure</p></div>
        </div>
    </section>

    <section class="capell-demo-pricing-paths">
        <article><span>Licensing</span><h2>Platform access</h2><p>Use Capell packages, core CMS primitives, and supported demo profiles.</p></article>
        <article><span>Support</span><h2>Technical advisory</h2><p>Review architecture, editor workflows, public rendering, and operational readiness.</p></article>
        <article><span>Implementation</span><h2>Scoped launch package</h2><p>Plan content models, migrations, frontend surfaces, and release verification.</p></article>
    </section>
</div>
HTML;
    }

    private function implementationPricingContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-implementation-plan">
    <section class="capell-demo-ledger-hero">
        <div>
            <p class="capell-demo-kicker">Implementation pricing</p>
            <h1>Implementation plan with commercial guardrails</h1>
            <p>A productized Capell implementation for teams that need a production CMS foundation, migration confidence, and a clear handover path.</p>
            <div class="capell-demo-price">from <strong>GBP 8,500</strong></div>
            <div class="capell-demo-actions">
                <a class="capell-demo-button" href="/contact/services">Schedule scoping</a>
                <a class="capell-demo-button capell-demo-button--secondary" href="/resources">Read launch guides</a>
            </div>
        </div>
        <aside class="capell-demo-scope-panel">
            <p class="capell-demo-brief__label">Scope confidence</p>
            <div><span>Inputs received</span><strong>Content model, sample exports, launch date</strong></div>
            <div><span>Pricing shape</span><strong>50% kickoff, 50% handover</strong></div>
            <div><span>Owner model</span><strong>Lead engineer plus architect</strong></div>
        </aside>
    </section>

    <section class="capell-demo-ledger">
        <div class="capell-demo-ledger__phase"><span>Week 1</span><strong>Audit</strong><p>Content, package surface, roles, redirects, and operational constraints.</p></div>
        <div class="capell-demo-ledger__phase"><span>Week 2-3</span><strong>Architecture</strong><p>Types, layouts, cache rules, preview paths, and package-owned rendering.</p></div>
        <div class="capell-demo-ledger__phase"><span>Week 4-6</span><strong>Migration</strong><p>Imports, media handling, validation reports, and rejected-record review.</p></div>
        <div class="capell-demo-ledger__phase"><span>Week 7-8</span><strong>Handover</strong><p>Editor training, developer notes, release checks, and post-launch ownership.</p></div>
    </section>

    <section class="capell-demo-inclusion-ledger">
        <div>
            <h2>Included</h2>
            <ul>
                <li>Content type and layout modelling</li>
                <li>Migration scripts and validation reports</li>
                <li>Theme integration and responsive QA</li>
                <li>Editor training and launch checklist</li>
            </ul>
        </div>
        <div>
            <h2>Guardrails</h2>
            <ul>
                <li>Custom package builds are scoped separately</li>
                <li>Third-party license fees stay outside delivery</li>
                <li>Design-system rewrites need explicit approval</li>
                <li>Retained support starts after handover</li>
            </ul>
        </div>
        <aside>
            <span>Commercial rule</span>
            <strong>Every scope change gets priced before work starts.</strong>
        </aside>
    </section>
</div>
HTML;
    }

    private function resourcesHubContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-resources-library">
    <section class="capell-demo-library-hero">
        <div>
            <p class="capell-demo-kicker">Resources</p>
            <h1>Resource library for Capell builders</h1>
            <p>Guides, architecture notes, launch checklists, and developer references for teams building Laravel and Filament CMS platforms with Capell.</p>
        </div>
        <form class="capell-demo-library-filter" action="/resources" method="get">
            <label>
                <span>Search resources</span>
                <input name="q" type="search" placeholder="Migration, layouts, schema">
            </label>
            <div>
                <button type="button">Guides</button>
                <button type="button">Architecture</button>
                <button type="button">Checklists</button>
            </div>
        </form>
    </section>

    <section class="capell-demo-library-grid">
        <article class="capell-demo-lead-feature">
            <p class="capell-demo-kicker">Featured guide</p>
            <h2>Scaling Laravel CMS architecture for 1M+ records</h2>
            <p>Structure page types, media, imports, search, cache, and static generation before content volume stops being theoretical.</p>
            <span>Architecture note - 12 min read</span>
        </article>
        <aside class="capell-demo-category-rail">
            <a href="/resources">Architecture<span>Models, packages, cache</span></a>
            <a href="/resources">Patterns<span>Layouts, forms, editors</span></a>
            <a href="/resources">Schema<span>SEO and AI discovery</span></a>
            <a href="/resources">Case studies<span>Launch breakdowns</span></a>
        </aside>
    </section>

    <section class="capell-demo-resource-index">
        <div class="capell-demo-resource-index__heading">
            <p class="capell-demo-kicker">Resource index</p>
            <h2>Recent technical notes</h2>
        </div>
        <article><span>Migration</span><h3>Designing imports editors can trust</h3><p>Validate source rows, preserve redirects, and keep rejected records explainable.</p><em>9 min</em></article>
        <article><span>Publishing</span><h3>Approval workflows without admin leakage</h3><p>Keep draft tooling private while public pages stay clean and cacheable.</p><em>7 min</em></article>
        <article><span>Theme systems</span><h3>Package-owned frontend rendering</h3><p>Build reusable public surfaces without coupling them to Filament screens.</p><em>11 min</em></article>
        <article><span>SEO</span><h3>Making CMS pages discoverable by default</h3><p>Use metadata, sitemap rules, AI discovery profiles, and explicit exclusions.</p><em>8 min</em></article>
    </section>
</div>
HTML;
    }

    private function hasExistingMedia(Model&HasMedia $model, BackedEnum|string $collection): bool
    {
        $model->unsetRelation('media');

        return $model->getMedia($this->mediaCollectionName($collection))->isNotEmpty();
    }

    private function mediaCollectionName(BackedEnum|string $collection): string
    {
        return $collection instanceof BackedEnum ? (string) $collection->value : $collection;
    }

    private function createFeatures(Site $site): Collection
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

                $content->translations()->firstOrCreate([
                    'language_id' => $language->id,
                ], [
                    'title' => $feature['title'],
                    'content' => $feature['content'],
                ]);
            });
        }

        return $contentFeatures;
    }

    private function createTestimonials(Collection $languages): Collection
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

        $testimonialType = Type::query()->updateOrCreate([
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

            $content->translations()->createMany(
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

    private function createTeamMembers(Collection $languages): Collection
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

            $content->translations()->createMany(
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

    private function createWidgetMedia(Widget $model, ?string $name = null, string $type = 'image', BackedEnum|string $collection = MediaCollectionEnum::Image): Media
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

        // Create content and link via WidgetAsset
        $content = $this->contentModel::create([
            'name' => str($filenameBase)->title(),
        ]);

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
    private function randomItem(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}
