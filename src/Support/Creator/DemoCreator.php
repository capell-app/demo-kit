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
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Media;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\Core\Support\Creator\PageCreator;
use Capell\DemoKit\Actions\DummyContentGeneratorAction;
use Capell\DemoKit\Support\DemoContentPool;
use Capell\LayoutBuilder\Enums\ActionLinkEnum;
use Capell\LayoutBuilder\Enums\ContentTypeEnum;
use Capell\LayoutBuilder\Enums\ElementComponentEnum;
use Capell\LayoutBuilder\Enums\ElementTypeEnum;
use Capell\LayoutBuilder\Enums\FrontendComponentKeyEnum;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Models\Element;
use Capell\LayoutBuilder\Models\ElementAsset;
use Capell\LayoutBuilder\Support\Creator\ElementCreator;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Capell\Navigation\Models\Navigation;
use Capell\Navigation\Support\Creator\NavigationCreator;
use Error;
use Exception;
use FilesystemIterator;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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

    private const FormBuilderPackage = 'capell-app/form-builder';

    private const StandardFooterPageNames = [
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
    private static array $demoImageFilenames = [];

    /** @var class-string<Model> */
    private readonly string $contentModel;

    /** @var class-string<Element> */
    private readonly string $elementModel;

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
        $this->typeModel = Blueprint::class;
        $this->elementModel = Element::class;
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
        ?Blueprint $type = null,
        ?Layout $layout = null,
        bool $createMedia = true,
        ?PageCreatable $pageCreator = null,
    ): Pageable {
        $languages ??= $site->languages;
        $pageCreator ??= new PageCreator;

        $name = $this->canonicalDemoPageName(Str::title($data['name']['en']));
        $layout ??= $this->layoutForDemoPage($name);

        if ($name === 'Contact') {
            $this->ensureContactFormIntegration($site);
        }

        $pageData = [
            'name' => $name,
            'user_id' => $this->author?->getKey(),
            'blueprint_id' => $type?->getKey(),
            'layout_id' => $layout?->getKey(),
            'meta' => $this->demoPageMeta($name),
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
                'summary' => $this->demoPageSummary($name),
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

    public function createContentWidget(Collection $languages): Element
    {
        $siteId = Site::query()->default()?->value('id');

        $type = resolve(TypeCreator::class)->contentBuilderElementType();

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'example-content'], [
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
                                /** @param Blueprint $query */
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

    public function createSplitContentWidget(Collection $languages): Element
    {
        $siteId = Site::query()->default()?->value('id');

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'example-split-content'], [
            'name' => 'Example Split Content',
            'blueprint_id' => $this->typeModel::query()->firstWhere(['key' => ElementTypeEnum::SectionBuilder, 'type' => LayoutTypeEnum::Element])->id,
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
                                /** @param Blueprint $query */
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

    public function createBannerImageWidget(Collection $languages): Element
    {
        $widget = resolve(ElementCreator::class)->bannerImageElement();

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

    public function createGalleryWidget(): Element
    {
        $widget = resolve(ElementCreator::class)->galleryElement();

        if ($widget->assets()->exists()) {
            return $widget;
        }

        for ($i = 1; $i <= 5; $i++) {
            $this->createWidgetMedia($widget);
        }

        return $widget;
    }

    public function createPageCardsWidget(Pageable $page, string $container = 'main', int $occurrence = 1): Element
    {
        $widget = resolve(ElementCreator::class)->pagesCardElement();

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

        $relatedPages->each(
            fn (Page $relatedPage): ElementAsset => $this->createPageElementAsset($widget, $page, $container, $occurrence, $relatedPage),
        );

        return $widget;
    }

    public function createFaqWidget(Collection $languages): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', 'assets');

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'faq'], [
            'key' => 'faq',
            'name' => __('capell-admin::generic.faq'),
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'icon' => 'heroicon-m-question-mark-circle',
                'component' => ElementComponentEnum::AssetAccordion,
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

    public function createMediaCarouselWidget(): Element
    {
        $widget = resolve(ElementCreator::class)->mediaCarouselElement();

        if ($widget->assets()->exists()) {
            return $widget;
        }

        for ($i = 1; $i <= 7; $i++) {
            $this->createWidgetMedia($widget);
        }

        $this->createWidgetMedia($widget, type: 'video');

        return $widget;
    }

    public function createStaticNavigationWidget(Collection $languages, Site $site): Element
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
                /** @param  Blueprint  $query */
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

        $widgetType = resolve(TypeCreator::class)->navigationElementType();

        $navigationType = $this->typeModel::query()->navigationType()->default()->first();
        if ($navigationType === null) {
            $navigationType = resolve(BlueprintCreator::class)->createNavigationType();
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
        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'example-navigation'], [
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

    public function createContentsWidget(Element $widget, Pageable $page, string $container, int $occurrence = 1, ?Blueprint $type = null): void
    {
        $pageElementAssets = $widget->assets()->where([
            'pageable_id' => $page->getKey(),
            'pageable_type' => $page->getMorphClass(),
            'container' => $container,
            'occurrence' => $occurrence,
        ])
            ->exists();

        if ($pageElementAssets) {
            return;
        }

        if (! $type instanceof Blueprint) {
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
                                    /** @param Blueprint $query */
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
                                    /** @param Blueprint $query */
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

    public function createClientLogosWidget(Collection $languages): Element
    {
        $widget = Element::query()->firstOrCreate([
            'key' => 'client-logos',
        ], [
            'name' => 'Client Logos',
            'blueprint_id' => $this->typeModel::query()->firstWhere(['key' => ElementTypeEnum::Assets, 'type' => LayoutTypeEnum::Element])->id,
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

    public function createBusinessFeaturesWidget(Site $site): Element
    {
        $widget = Element::query()->firstOrCreate([
            'key' => 'business-features',
        ], [
            'name' => 'Business Features',
            'blueprint_id' => $this->typeModel::query()->firstWhere(['key' => ElementTypeEnum::Sections, 'type' => LayoutTypeEnum::Element])->id,
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

    public function createBannersWidget(): Element
    {
        $creator = resolve(ElementCreator::class);
        $widget = $creator->bannerElement();

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

    public function createTestimonialsWidget(Collection $languages): Element
    {
        $widgetCreator = resolve(ElementCreator::class);
        $widget = $widgetCreator->testimonialsElement();

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

    public function createStatisticsWidget(): Element
    {
        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'statistics'], [
            'name' => 'Statistic Blocks',
            'blueprint_id' => $this->typeModel::query()->firstWhere(['key' => ElementTypeEnum::Assets, 'type' => LayoutTypeEnum::Element])->id,
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

    public function createTeamPortfolioWidget(Collection $languages): Element
    {
        $type = $this->typeModel::query()
            ->where([
                'key' => ElementTypeEnum::Sections,
                'type' => LayoutTypeEnum::Element,
            ])
            ->first();

        if ($type === null) {
            $type = resolve(TypeCreator::class)->contentsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'team-portfolio'], [
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

    public function createModernFeatureListWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'modern-feature-list'], [
            'name' => 'Modern Feature List',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApFeatureList,
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

    public function createModernTeamMembersWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'modern-team-members'], [
            'name' => 'Modern Team Members',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApTeamMembers,
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

    public function createModernPricingTableWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'modern-pricing-table'], [
            'name' => 'Modern Pricing Table',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApPricingTable,
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

    public function createModernTestimonialsWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'modern-testimonials'], [
            'name' => 'Modern Testimonials',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApTestimonials,
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

    public function createModernFaqWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'modern-faq'], [
            'name' => 'Modern FAQ Section',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApFaqSection,
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

    public function createModernStatsSectionWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'modern-stats'], [
            'name' => 'Modern Stats Section',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApStatsSection,
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

    public function createModernAlternatingContentWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'modern-alternating-content'], [
            'name' => 'Modern Alternating Content',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApAlternatingContent,
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

    public function createModernProcessStepsWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'modern-process-steps'], [
            'name' => 'Modern Process Steps',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApProcessSteps,
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

    public function createModernImageGalleryWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($widgetType === null) {
            $widgetType = resolve(TypeCreator::class)->assetsElementType();
        }

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'modern-image-gallery'], [
            'name' => 'Modern Image Gallery',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApImageGallery,
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

    public function createHomepageHeroCommandCenterWidget(): Element
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-hero-command-center',
            name: 'Capell Homepage Command Center Hero',
            content: $this->homepageHeroCommandCenterHtml(),
        );
    }

    public function createHomepageProofStripWidget(): Element
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-proof-strip',
            name: 'Capell Homepage Proof Strip',
            content: $this->homepageProofStripHtml(),
        );
    }

    public function createHomepageDemoShowcaseWidget(): Element
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-demo-showcase',
            name: 'Capell Homepage Demo Showcase',
            content: $this->homepageDemoShowcaseHtml(),
        );
    }

    public function createHomepageMarketplaceWidget(): Element
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-extension-marketplace-showcase',
            name: 'Extension Marketplace Showcase',
            content: $this->homepageMarketplaceHtml(),
        );
    }

    public function createHomepageTechnicalPipelineWidget(): Element
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-technical-pipeline',
            name: 'Capell Homepage Technical Pipeline',
            content: $this->homepageTechnicalPipelineHtml(),
        );
    }

    public function createHomepageRouteSplitWidget(): Element
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-route-split',
            name: 'Capell Homepage Route Split',
            content: $this->homepageRouteSplitHtml(),
        );
    }

    public function createHomepageFinalCtaWidget(): Element
    {
        return $this->createHomepageSnippetWidget(
            key: 'capell-home-final-cta',
            name: 'Capell Homepage Final CTA',
            content: $this->homepageFinalCtaHtml(),
        );
    }

    public function createApHeroBannerWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::HeroBanner)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
                ->firstWhere('key', ElementTypeEnum::Default);

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'ap-hero-banner'], [
            'name' => 'AP Hero Banner',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApHeroBanner,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Product Hero',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApHeroBanner,
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

    public function createApCardGridWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::CardGrid)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
                ->firstWhere('key', ElementTypeEnum::Default);

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'ap-card-grid'], [
            'name' => 'Capell Capability Cards',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApCardGrid,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Capability Cards',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApCardGrid,
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

    public function createApFeatureListWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::FeatureList)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
                ->firstWhere('key', ElementTypeEnum::Default);

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'ap-feature-list'], [
            'name' => 'Capell Workflow Feature List',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApFeatureList,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Workflow Feature List',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApFeatureList,
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

    public function createFeatureListWidget(): Element
    {
        $widget = resolve(ElementCreator::class)->featuresElement();

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

    public function createApCtaSectionWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::CTASection)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
                ->firstWhere('key', ElementTypeEnum::Default);

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'ap-cta-section'], [
            'name' => 'AP CTA Section',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApCTASection,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Showcase CTA',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApCTASection,
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

    public function createApImageGalleryWidget(): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::ImageGallery)
            ?? $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
                ->firstWhere('key', ElementTypeEnum::Default);

        $widget = $this->elementModel::query()->firstOrCreate(['key' => 'ap-image-gallery'], [
            'name' => 'AP Image Gallery',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApImageGallery,
            ],
        ]);

        $widget->forceFill([
            'name' => 'Capell Media Gallery',
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApImageGallery,
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

    private function createPageElementAsset(Element $element, Pageable $page, string $container, int $occurrence, Model $asset): ElementAsset
    {
        return DB::transaction(
            fn (): ElementAsset => $element->assets()->createOrFirst([
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

    private function layoutForDemoPage(string $name): ?Layout
    {
        $name = $this->canonicalDemoPageName($name);

        $templateLayouts = [
            'About Us' => ['capell-demo-about', 'Capell Demo About', true],
            'Homepage 2' => ['capell-demo-homepage-2', 'Capell Demo Homepage 2', false],
            'Services' => ['capell-demo-services', 'Capell Demo Services', true],
            'Team' => ['capell-demo-team', 'Capell Demo Team', true],
            'FAQ' => ['capell-demo-faq-no-hero', 'Capell Demo FAQ Without Hero', true],
            'Pricing' => ['capell-demo-pricing-no-hero', 'Capell Demo Pricing Without Hero', true],
            'Testimonials' => ['capell-demo-testimonials', 'Capell Demo Testimonials', true],
            'Projects' => ['capell-demo-projects', 'Capell Demo Projects', true],
            'Project Detail' => ['capell-demo-project-detail', 'Capell Demo Project Detail', true],
            'Blog' => ['capell-demo-blog', 'Capell Demo Blog', true],
            'Home, Buildings and Architecture' => ['capell-demo-article-no-hero', 'Capell Demo Article Without Hero', true],
        ];

        if (array_key_exists($name, $templateLayouts)) {
            [$key, $layoutName, $withBreadcrumbs] = $templateLayouts[$name];

            return $this->demoPageLayout($key, $layoutName, $withBreadcrumbs);
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
                            'elements' => [
                                ['element_key' => 'breadcrumbs'],
                                ['element_key' => 'page-content'],
                            ],
                        ],
                    ],
                    'elements' => ['breadcrumbs', 'page-content'],
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
                    'elements' => [
                        ['element_key' => 'breadcrumbs'],
                    ],
                ],
                'contact-copy' => [
                    'meta' => [
                        'colspan' => 7,
                        'spacing' => 'lg',
                        'html_class' => 'capell-demo-contact-copy-column',
                    ],
                    'elements' => [
                        ['element_key' => 'page-content'],
                    ],
                ],
                'contact-form' => [
                    'meta' => [
                        'colspan' => 5,
                        'spacing' => 'lg',
                        'html_class' => 'capell-demo-contact-form-column',
                    ],
                    'elements' => [
                        [
                            'element_key' => 'contact-form',
                            'form_handle' => 'contact',
                        ],
                    ],
                ],
            ],
            'elements' => ['breadcrumbs', 'page-content', 'contact-form'],
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

    private function demoPageLayout(string $key, string $name, bool $withBreadcrumbs): Layout
    {
        $elements = $withBreadcrumbs
            ? [
                ['element_key' => 'breadcrumbs'],
                [
                    'element_key' => 'page-content',
                    'meta' => [
                        'page_content' => ['content'],
                    ],
                ],
            ]
            : [
                [
                    'element_key' => 'page-content',
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
                    'elements' => $elements,
                ],
            ],
            'elements' => collect($elements)
                ->pluck('element_key')
                ->values()
                ->all(),
            'meta' => [
                'description' => 'A Capell demo page template rendered through reusable page-content layout elements.',
            ],
            'default' => false,
            'status' => true,
        ];

        $layout = $this->layoutModel::query()->firstOrCreate(['key' => $key], $attributes);
        $layout->forceFill($attributes)->save();

        return $layout;
    }

    private function ensureContactFormIntegration(Site $site): void
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

        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Default);

        $widgetType ??= $this->typeModel::query()
            ->where('type', LayoutTypeEnum::Element->value)
            ->firstWhere('key', ElementTypeEnum::Default->value);

        if (! $widgetType instanceof Blueprint) {
            return;
        }

        Element::query()->updateOrCreate(
            ['key' => 'contact-form'],
            [
                'name' => 'Contact form',
                'blueprint_id' => $widgetType->getKey(),
                'component' => 'capell-form-builder::element.form',
                'is_livewire' => true,
                'meta' => [
                    'component' => 'capell-form-builder::element.form',
                    'form_handle' => 'contact',
                ],
                'status' => true,
            ],
        );
    }

    private function createHomepageSnippetWidget(string $key, string $name, string $content): Element
    {
        $widgetType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Default);

        $widgetType ??= $this->typeModel::query()
            ->where('type', LayoutTypeEnum::Element->value)
            ->firstWhere('key', ElementTypeEnum::Default->value);

        throw_unless($widgetType instanceof Blueprint, Exception::class, 'Unable to find default widget type.');

        $widget = Element::query()->firstOrCreate(['key' => $key], [
            'name' => $name,
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::Snippet->value,
                'heading_size' => 'h2',
                'content_divider' => false,
                'margin' => ['none'],
            ],
        ]);

        $widget->forceFill([
            'name' => $name,
            'blueprint_id' => $widgetType->id,
            'meta' => [
                'component' => ElementComponentEnum::Snippet->value,
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
        return <<<'HTML_WRAP'
        <div class="capell-home capell-home-hero">
            <section class="capell-home-hero__copy">
                <p class="capell-home-kicker">Capell CMS</p>
                <h1>Composable content infrastructure for Laravel teams</h1>
                <p>Ship multi-site CMS platforms without template sprawl: typed content, editor-owned layouts, package-owned rendering, static output, and diagnostics in one Laravel-native system.</p>
                <div class="capell-home-actions">
                    <a class="capell-home-button" href="/resources">Explore the demo</a>
                    <a class="capell-home-button capell-home-button--secondary" href="/pricing">View pricing</a>
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
        HTML_WRAP;
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
        return <<<'HTML_WRAP'
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
        HTML_WRAP;
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
    <a href="/pricing">
        <span>Pricing</span>
        <strong>Licensing and support for production teams</strong>
        <em>Plan the rollout</em>
    </a>
    <a href="/contact#scoping">
        <span>Contact</span>
        <strong>Architecture, migration, and package support</strong>
        <em>Start scoping</em>
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
    <a class="capell-home-button" href="/contact#scoping">Start implementation scoping</a>
</div>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private function demoPageMeta(string $name): array
    {
        $name = $this->canonicalDemoPageName($name);

        $withoutHero = in_array($name, [
            'FAQ',
            'Pricing',
            'Project Detail',
            'Home, Buildings and Architecture',
        ], true);

        return [
            'show_hero' => ! $withoutHero,
            'hero_style' => match ($name) {
                'Homepage 2' => 'immersive',
                'About Us', 'Services', 'Team', 'Testimonials', 'Projects', 'Blog' => 'compact',
                default => 'default',
            },
            'hero_asset_source' => 'mixed',
            'header_over_hero' => $name === 'Homepage 2',
        ];
    }

    private function demoPageContent(string $name, string $languageCode): ?string
    {
        $name = $this->canonicalDemoPageName($name);

        if ($languageCode !== 'en') {
            return null;
        }

        return match ($name) {
            'About Us' => $this->showcaseAboutContent(),
            'Homepage 2' => $this->showcaseHomepageTwoContent(),
            'Contact' => $this->contactIndexContent(),
            'Services' => $this->contactServicesContent(),
            'Team' => $this->showcaseTeamContent(),
            'FAQ' => $this->showcaseFaqContent(),
            'Pricing' => $this->pricingIndexContent(),
            'Testimonials' => $this->showcaseTestimonialsContent(),
            'Projects' => $this->showcaseProjectsContent(),
            'Project Detail' => $this->showcaseProjectDetailContent(),
            'Blog' => $this->showcaseBlogContent(),
            'Home, Buildings and Architecture' => $this->showcaseSinglePostContent(),
            'Implementation' => $this->implementationPricingContent(),
            'Resources' => $this->resourcesHubContent(),
            'Integrations' => $this->integrationsIndexContent(),
            'Locations' => $this->locationsIndexContent(),
            'Compliance' => $this->complianceLocationContent(),
            'Sustainability' => $this->sustainabilityLocationContent(),
            'Partners' => $this->partnersIndexContent(),
            'Roadmap' => $this->roadmapIndexContent(),
            'Governance' => $this->governanceIndexContent(),
            'Training' => $this->trainingIndexContent(),
            default => null,
        };
    }

    private function demoPageSummary(string $name): ?string
    {
        $name = $this->canonicalDemoPageName($name);

        return match ($name) {
            'Compliance' => 'Regional compliance operations with publishing controls, evidence ownership, and structured local governance.',
            'Sustainability' => 'Local sustainability reporting that keeps regional initiatives, metrics, and proof points consistent across the network.',
            default => null,
        };
    }

    private function canonicalDemoPageName(string $name): string
    {
        return match (Str::lower($name)) {
            'faq' => 'FAQ',
            'home, buildings and architecture' => 'Home, Buildings and Architecture',
            default => $name,
        };
    }

    private function contactIndexContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-contact-gateway">
    <section class="capell-demo-contact-intro">
        <p class="capell-demo-kicker">Contact us</p>
        <h2>Get in touch with the Capell team</h2>
        <p>Send a message about your CMS project, migration, integration work, or support needs. A clear contact page keeps the next step simple without sending visitors through child pages.</p>
    </section>

    <section class="capell-demo-contact-layout" aria-label="Contact options">
        <aside class="capell-demo-contact-details" aria-label="Contact details">
            <section>
                <h2>Contact details</h2>
                <dl>
                    <div><dt>Email</dt><dd><a href="mailto:hello@capell.app">hello@capell.app</a></dd></div>
                    <div><dt>Phone</dt><dd><a href="tel:+442045712840">+44 20 4571 2840</a></dd></div>
                    <div><dt>Response</dt><dd>Within 2 business days</dd></div>
                </dl>
            </section>

            <section>
                <h2>Address</h2>
                <address>
                    Capell Studio<br>
                    London<br>
                    United Kingdom
                </address>
            </section>

            <section>
                <h2>Office hours</h2>
                <p>Monday to Friday, 9:00 to 17:30 UK time.</p>
            </section>
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
                <a class="capell-demo-button" href="/contact#scoping">Book scoping call</a>
                <a class="capell-demo-button capell-demo-button--secondary" href="/pricing">See pricing</a>
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

    private function showcaseAboutContent(): string
    {
        return <<<'HTML_WRAP'
        <div class="capell-demo-page capell-demo-showcase-page capell-demo-showcase-page--about mx-auto max-w-none p-0 text-[#111827]">
            <section class="capell-demo-showcase-hero capell-demo-showcase-hero--compact relative isolate m-0 min-h-[min(64vh,40rem)] w-full max-w-none overflow-hidden bg-[linear-gradient(90deg,rgb(7_10_18_/_0.92),rgb(7_10_18_/_0.74),rgb(7_10_18_/_0.38)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center px-[max(1.5rem,calc((100vw_-_76rem)/2))] py-[clamp(4rem,8vw,8rem)] text-white">
                <p class="capell-demo-kicker">About Capell</p>
                <h1 class="max-w-[15ch] text-balance text-[clamp(3rem,8vw,6.5rem)] leading-[0.92] tracking-normal text-white">A CMS architecture team with a layout-builder product mindset</h1>
                <p class="max-w-[42rem] text-white/80">Capell combines Laravel package discipline, Filament editorial workflows, reusable public elements, and static delivery into one maintainable publishing platform.</p>
            </section>

            <section class="capell-demo-showcase-split mx-auto my-[clamp(4rem,8vw,8rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 items-start gap-[clamp(1.5rem,4vw,4rem)] lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <div>
                    <p class="capell-demo-kicker">Platform experience</p>
                    <h2 class="max-w-[20ch] text-[clamp(2rem,4vw,3.75rem)] leading-none tracking-normal">Experienced in flexible content systems</h2>
                    <p class="max-w-[68ch] text-[#4b5563] leading-7">Use Capell when a site needs more than pages and prose. The same model can power media-heavy marketing pages, resource libraries, navigation-led microsites, and governed multi-site publishing.</p>
                    <p class="max-w-[68ch] text-[#4b5563] leading-7">Editors get flexible composition. Developers keep clear boundaries. Visitors receive clean, fast public output.</p>
                    <div class="capell-demo-showcase-stats my-[clamp(2rem,6vw,5rem)] grid grid-cols-1 gap-px border border-[#d8dee8] bg-[#d8dee8] md:grid-cols-3">
                        <div class="min-h-36 bg-white p-5"><strong class="block text-[clamp(2rem,4vw,3.5rem)] leading-none">12+</strong><span>Page types</span></div>
                        <div class="min-h-36 bg-white p-5"><strong class="block text-[clamp(2rem,4vw,3.5rem)] leading-none">40+</strong><span>Widget elements</span></div>
                        <div class="min-h-36 bg-white p-5"><strong class="block text-[clamp(2rem,4vw,3.5rem)] leading-none">100+</strong><span>Media assets</span></div>
                    </div>
                </div>
                <aside class="capell-demo-showcase-collage grid grid-cols-2 gap-3 md:grid-cols-4 md:auto-rows-[minmax(9rem,18vw)]" aria-label="Capell content collage">
                    <span class="flex min-h-40 items-end border border-[#d8dee8] bg-[linear-gradient(180deg,rgb(7_10_18_/_0.05),rgb(7_10_18_/_0.72)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center p-4 font-extrabold text-white md:col-span-2 md:row-span-2">Page</span>
                    <span class="flex min-h-40 items-end border border-[#d8dee8] bg-[linear-gradient(180deg,rgb(7_10_18_/_0.05),rgb(7_10_18_/_0.72)),url('/images/capell-demo/capell-installer.png')] bg-cover bg-center p-4 font-extrabold text-white">Gallery</span>
                    <span class="flex min-h-40 items-end border border-[#d8dee8] bg-[linear-gradient(180deg,rgb(7_10_18_/_0.05),rgb(7_10_18_/_0.72)),url('/images/capell-demo/capell-site-layout-example.jpeg')] bg-cover bg-center p-4 font-extrabold text-white">Asset</span>
                    <span class="flex min-h-40 items-end border border-[#d8dee8] bg-[linear-gradient(180deg,rgb(7_10_18_/_0.05),rgb(7_10_18_/_0.72)),url('/images/capell-demo/capell-brand-system.png')] bg-cover bg-center p-4 font-extrabold text-white">Navigation</span>
                </aside>
            </section>

            <section class="capell-demo-showcase-process mx-auto my-[clamp(4rem,8vw,8rem)] w-[min(76rem,calc(100%_-_3rem))]">
                <p class="capell-demo-kicker">How we work</p>
                <h2 class="max-w-[20ch] text-[clamp(2rem,4vw,3.75rem)] leading-none tracking-normal">From content model to public page</h2>
                <ol class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <li class="border border-[#d8dee8] bg-white p-5"><span class="text-sm font-bold uppercase text-[#4b5563]">01</span><strong class="mt-3 block">Consultation</strong><p class="text-[#4b5563]">Audit content, routes, media, integrations, and editor ownership.</p></li>
                    <li class="border border-[#d8dee8] bg-white p-5"><span class="text-sm font-bold uppercase text-[#4b5563]">02</span><strong class="mt-3 block">Builder design</strong><p class="text-[#4b5563]">Define reusable elements that editors can safely compose.</p></li>
                    <li class="border border-[#d8dee8] bg-white p-5"><span class="text-sm font-bold uppercase text-[#4b5563]">03</span><strong class="mt-3 block">Migration and QA</strong><p class="text-[#4b5563]">Move content into Capell and verify the public output.</p></li>
                    <li class="border border-[#d8dee8] bg-white p-5"><span class="text-sm font-bold uppercase text-[#4b5563]">04</span><strong class="mt-3 block">Publish</strong><p class="text-[#4b5563]">Generate cacheable HTML and hand over the workflow.</p></li>
                </ol>
            </section>

            <section class="capell-demo-showcase-promo mx-auto my-[clamp(4rem,8vw,8rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 items-center gap-8 bg-[linear-gradient(90deg,rgb(7_10_18_/_0.9),rgb(7_10_18_/_0.58)),url('/images/capell-demo/capell-site-layout-example.jpeg')] bg-cover bg-center p-[clamp(2rem,5vw,4rem)] text-white md:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                    <p class="capell-demo-kicker">Capell promotion</p>
                    <h2 class="max-w-[20ch] text-[clamp(2rem,4vw,3.75rem)] leading-none tracking-normal text-white">Build a public site that editors can actually own</h2>
                    <p class="max-w-[68ch] text-white/75">Every major page section can be recreated with layout-builder elements, section assets, media, and navigation-aware content.</p>
                </div>
                <a class="capell-demo-button" href="/contact#scoping">Get the best route</a>
            </section>
        </div>
        HTML_WRAP;
    }

    private function showcaseHomepageTwoContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-showcase-page capell-demo-showcase-page--home-variant mx-auto max-w-none p-0 text-[#111827]">
    <section class="capell-demo-showcase-hero relative isolate grid min-h-[min(78vh,46rem)] w-full max-w-none grid-cols-1 items-center gap-[clamp(2rem,5vw,5rem)] overflow-hidden bg-[linear-gradient(90deg,rgb(7_10_18_/_0.92),rgb(7_10_18_/_0.74),rgb(7_10_18_/_0.38)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center px-[max(1.5rem,calc((100vw_-_76rem)/2))] py-[clamp(4rem,8vw,8rem)] text-white lg:grid-cols-[minmax(0,1.05fr)_minmax(18rem,0.95fr)]">
        <p class="capell-demo-kicker">Architecture &amp; content</p>
        <h1 class="max-w-[15ch] text-balance text-[clamp(3rem,8vw,6.5rem)] leading-[0.92] tracking-normal text-white">A second homepage for service-led Capell builds</h1>
        <p class="max-w-[42rem] text-white/80">This variation keeps the same Capell content but changes the rhythm: feature cards first, then services, team proof, project examples, pricing, and launch metrics.</p>
        <a class="capell-demo-button" href="/projects">View portfolio</a>
    </section>

    <section class="capell-demo-showcase-feature-row mx-auto my-[clamp(4rem,8vw,8rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 gap-4 md:grid-cols-3">
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><span class="text-sm font-bold uppercase text-[#4b5563]">01</span><h2 class="mt-4 text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Cost friendly</h2><p class="text-[#4b5563]">Reuse governed widgets rather than designing every page from scratch.</p></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)] md:translate-y-6"><span class="text-sm font-bold uppercase text-[#4b5563]">02</span><h2 class="mt-4 text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Communicative</h2><p class="text-[#4b5563]">Make page structure clear to editors, reviewers, and developers.</p></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><span class="text-sm font-bold uppercase text-[#4b5563]">03</span><h2 class="mt-4 text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Responsive design</h2><p class="text-[#4b5563]">Tailwind-friendly element layouts keep the public surface adaptable.</p></article>
    </section>

    <section class="capell-demo-showcase-services mx-auto my-[clamp(4rem,8vw,8rem)] w-[min(76rem,calc(100%_-_3rem))]">
        <div>
            <p class="capell-demo-kicker">Our services</p>
            <h2 class="max-w-[20ch] text-[clamp(2rem,4vw,3.75rem)] leading-none tracking-normal">Best service from Capell</h2>
            <p class="max-w-[68ch] text-[#4b5563] leading-7">Implementation support, frontend architecture, migration planning, editor workflow setup, and launch verification.</p>
        </div>
        <div class="capell-demo-showcase-card-grid mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><h3 class="text-[clamp(1.15rem,2vw,1.55rem)] leading-tight">Layout architecture</h3><p class="text-[#4b5563] leading-7">Reusable sections, galleries, cards, forms, and CTAs.</p></article>
            <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)] md:translate-y-6"><h3 class="text-[clamp(1.15rem,2vw,1.55rem)] leading-tight">Content migration</h3><p class="text-[#4b5563] leading-7">Move existing pages, assets, redirects, and resource libraries safely.</p></article>
            <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><h3 class="text-[clamp(1.15rem,2vw,1.55rem)] leading-tight">Publishing workflow</h3><p class="text-[#4b5563] leading-7">Preview, approval, scheduling, cache generation, and release checks.</p></article>
            <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><h3 class="text-[clamp(1.15rem,2vw,1.55rem)] leading-tight">Package integration</h3><p class="text-[#4b5563] leading-7">Blog, search, forms, navigation, analytics, and access control.</p></article>
        </div>
    </section>

    <section class="capell-demo-showcase-pricing-strip mx-auto my-[clamp(4rem,8vw,8rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 gap-px border border-[#d8dee8] bg-[#d8dee8] md:grid-cols-3">
        <article class="bg-white p-5"><span class="text-sm font-bold uppercase text-[#4b5563]">Standard plan</span><strong class="block text-3xl">Developer</strong><p class="text-[#4b5563]">Evaluation and proof-of-concept builds.</p></article>
        <article class="bg-white p-5"><span class="text-sm font-bold uppercase text-[#4b5563]">Premium plan</span><strong class="block text-3xl">Agency</strong><p class="text-[#4b5563]">Production support for client delivery.</p></article>
        <article class="bg-white p-5"><span class="text-sm font-bold uppercase text-[#4b5563]">Ultimate plan</span><strong class="block text-3xl">Enterprise</strong><p class="text-[#4b5563]">Governed rollout, support, and procurement.</p></article>
    </section>
</div>
HTML;
    }

    private function showcaseTeamContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-showcase-page capell-demo-showcase-page--team mx-auto max-w-none p-0 text-[#111827]">
    <section class="capell-demo-showcase-hero capell-demo-showcase-hero--compact relative isolate m-0 min-h-[min(64vh,40rem)] w-full max-w-none overflow-hidden bg-[linear-gradient(90deg,rgb(7_10_18_/_0.92),rgb(7_10_18_/_0.74),rgb(7_10_18_/_0.38)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center px-[max(1.5rem,calc((100vw_-_76rem)/2))] py-[clamp(4rem,8vw,8rem)] text-white">
        <p class="capell-demo-kicker">Meet our team</p>
        <h1 class="max-w-[15ch] text-balance text-[clamp(3rem,8vw,6.5rem)] leading-[0.92] tracking-normal text-white">Implementation specialists for Capell websites</h1>
        <p class="max-w-[42rem] text-white/80">A team page should prove capability, not just show profiles. These roles map to the actual work needed to build flexible Capell sites.</p>
    </section>
    <section class="capell-demo-showcase-team-grid mx-auto my-[clamp(4rem,8vw,8rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.12),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Strategy</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Mara Ellison</h2><p class="text-[#4b5563]">CMS architecture lead</p></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)] md:translate-y-6"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.1),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-installer.png')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Frontend</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Jon Bell</h2><p class="text-[#4b5563]">Tailwind and public rendering</p></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.1),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-site-layout-example.jpeg')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Publishing</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Ada Morris</h2><p class="text-[#4b5563]">Filament workflow specialist</p></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.12),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-brand-system.png')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Migration</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Cal Hart</h2><p class="text-[#4b5563]">Content import engineer</p></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.12),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">QA</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Nia Porter</h2><p class="text-[#4b5563]">Release and cache verification</p></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.1),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-installer.png')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Support</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Eli Stone</h2><p class="text-[#4b5563]">Production support lead</p></article>
    </section>
    <section class="capell-demo-showcase-stats capell-demo-showcase-stats--band mx-auto my-[clamp(2rem,6vw,5rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 gap-px border border-[#d8dee8] bg-[#d8dee8] md:grid-cols-4">
        <div class="min-h-36 bg-white p-5"><strong class="block text-[clamp(2rem,4vw,3.5rem)] leading-none">8+</strong><span>Specialist roles</span></div>
        <div class="min-h-36 bg-white p-5"><strong class="block text-[clamp(2rem,4vw,3.5rem)] leading-none">45+</strong><span>Packages checked</span></div>
        <div class="min-h-36 bg-white p-5"><strong class="block text-[clamp(2rem,4vw,3.5rem)] leading-none">4</strong><span>Release gates</span></div>
        <div class="min-h-36 bg-white p-5"><strong class="block text-[clamp(2rem,4vw,3.5rem)] leading-none">1</strong><span>Clear owner model</span></div>
    </section>
</div>
HTML;
    }

    private function showcaseFaqContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-showcase-page capell-demo-showcase-page--faq mx-auto max-w-none p-0 text-[#111827]">
    <section class="capell-demo-showcase-hero capell-demo-showcase-hero--compact relative isolate m-0 min-h-[min(64vh,40rem)] w-full max-w-none overflow-hidden bg-[linear-gradient(90deg,rgb(7_10_18_/_0.92),rgb(7_10_18_/_0.74),rgb(7_10_18_/_0.38)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center px-[max(1.5rem,calc((100vw_-_76rem)/2))] py-[clamp(4rem,8vw,8rem)] text-white">
        <p class="capell-demo-kicker">FAQ</p>
        <h1 class="max-w-[15ch] text-balance text-[clamp(3rem,8vw,6.5rem)] leading-[0.92] tracking-normal text-white">You have questions?</h1>
        <p class="max-w-[42rem] text-white/80">This page intentionally works without a large hero image. It proves Capell can render dense support content in a calmer page template.</p>
    </section>
    <section class="capell-demo-showcase-faq mx-auto my-[clamp(4rem,8vw,8rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <h2 class="text-[clamp(2rem,4vw,3.75rem)] leading-none">Capell architecture</h2>
            <details class="border border-[#d8dee8] bg-white p-4" open><summary class="cursor-pointer font-extrabold">Can the header sit above the hero instead of overlaying it?</summary><p class="text-[#4b5563]">Yes. The theme now has a header-over-hero switch so teams can use either an overlay treatment or a normal document flow header.</p></details>
            <details class="border border-[#d8dee8] bg-white p-4"><summary class="cursor-pointer font-extrabold">Can a page skip the hero entirely?</summary><p class="text-[#4b5563]">Yes. Pages can render directly into content, FAQ, article, pricing, or project layouts without needing a hero widget.</p></details>
            <details class="border border-[#d8dee8] bg-white p-4"><summary class="cursor-pointer font-extrabold">Does public output leak editor controls?</summary><p class="text-[#4b5563]">No. Public pages render clean frontend components while editor and Filament concerns stay private.</p></details>
        </div>
        <div>
            <h2 class="text-[clamp(2rem,4vw,3.75rem)] leading-none">Builder services</h2>
            <details class="border border-[#d8dee8] bg-white p-4"><summary class="cursor-pointer font-extrabold">Can we keep existing content?</summary><p class="text-[#4b5563]">Yes. The goal is to preserve content intent and improve layout, hierarchy, and reusable structure.</p></details>
            <details class="border border-[#d8dee8] bg-white p-4"><summary class="cursor-pointer font-extrabold">Can we model project and blog pages?</summary><p class="text-[#4b5563]">Yes. This demo includes project listing, project detail, blog index, and single article page coverage.</p></details>
        </div>
    </section>
</div>
HTML;
    }

    private function showcaseTestimonialsContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-showcase-page capell-demo-showcase-page--testimonials mx-auto max-w-none p-0 text-[#111827]">
    <section class="capell-demo-showcase-hero capell-demo-showcase-hero--compact relative isolate m-0 min-h-[min(64vh,40rem)] w-full max-w-none overflow-hidden bg-[linear-gradient(90deg,rgb(7_10_18_/_0.92),rgb(7_10_18_/_0.74),rgb(7_10_18_/_0.38)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center px-[max(1.5rem,calc((100vw_-_76rem)/2))] py-[clamp(4rem,8vw,8rem)] text-white">
        <p class="capell-demo-kicker">Testimonials</p>
        <h1 class="max-w-[15ch] text-balance text-[clamp(3rem,8vw,6.5rem)] leading-[0.92] tracking-normal text-white">What Capell builders say</h1>
        <p class="max-w-[42rem] text-white/80">Reusable testimonial sections can act as proof bands, card grids, carousel content, or supporting evidence beside service pages.</p>
    </section>
    <section class="capell-demo-showcase-testimonial-grid mx-auto my-[clamp(4rem,8vw,8rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.12),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center"></div><p class="text-[#4b5563]">Capell replaced rigid templates with governed elements our editors can actually compose.</p><strong class="block">Mara Ellison</strong><span>Senior Laravel developer</span></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.1),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-installer.png')] bg-cover bg-center"></div><p class="text-[#4b5563]">The public output stays clean while the editorial workflow remains flexible.</p><strong class="block">Jon Bell</strong><span>Frontend lead</span></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.1),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-site-layout-example.jpeg')] bg-cover bg-center"></div><p class="text-[#4b5563]">We can build resource hubs, landing pages, and service pages from the same content system.</p><strong class="block">Ada Morris</strong><span>Publishing owner</span></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.12),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-brand-system.png')] bg-cover bg-center"></div><p class="text-[#4b5563]">The package boundaries make implementation work easier to estimate and support.</p><strong class="block">Cal Hart</strong><span>Migration engineer</span></article>
    </section>
</div>
HTML;
    }

    private function showcaseProjectsContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-showcase-page capell-demo-showcase-page--projects mx-auto max-w-none p-0 text-[#111827]">
    <section class="capell-demo-showcase-hero capell-demo-showcase-hero--compact relative isolate m-0 min-h-[min(64vh,40rem)] w-full max-w-none overflow-hidden bg-[linear-gradient(90deg,rgb(7_10_18_/_0.92),rgb(7_10_18_/_0.74),rgb(7_10_18_/_0.38)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center px-[max(1.5rem,calc((100vw_-_76rem)/2))] py-[clamp(4rem,8vw,8rem)] text-white">
        <p class="capell-demo-kicker">Latest project</p>
        <h1 class="max-w-[15ch] text-balance text-[clamp(3rem,8vw,6.5rem)] leading-[0.92] tracking-normal text-white">Capell implementation project library</h1>
        <p class="max-w-[42rem] text-white/80">Project listings show that Capell can mix categories, galleries, cards, metadata, and detail routes without hard-coded portfolio templates.</p>
    </section>
    <nav class="capell-demo-showcase-filter mx-auto mt-[clamp(4rem,8vw,8rem)] flex w-[min(76rem,calc(100%_-_3rem))] flex-wrap gap-3" aria-label="Project filters"><a class="border border-[#111827] bg-[#111827] px-3 py-2 text-sm font-extrabold text-white" href="/projects">All</a><a class="border border-[#d8dee8] bg-white px-3 py-2 text-sm font-extrabold text-[#4b5563]" href="/projects">Migration</a><a class="border border-[#d8dee8] bg-white px-3 py-2 text-sm font-extrabold text-[#4b5563]" href="/projects">Layout Builder</a><a class="border border-[#d8dee8] bg-white px-3 py-2 text-sm font-extrabold text-[#4b5563]" href="/projects">Publishing</a></nav>
    <section class="capell-demo-showcase-project-grid mx-auto mb-[clamp(4rem,8vw,8rem)] mt-6 grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.12),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Layout Builder</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Flexible marketing site</h2><p class="text-[#4b5563]">Hero slideshow, galleries, proof, resource cards, and contact strip.</p></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)] md:translate-y-6"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.1),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-installer.png')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Migration</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Content library rebuild</h2><p class="text-[#4b5563]">Resource hub, media assets, navigation trees, and search-ready pages.</p></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.1),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-site-layout-example.jpeg')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Publishing</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Governed multi-site estate</h2><p class="text-[#4b5563]">Domains, languages, approval workflows, and cache generation.</p></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.12),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-brand-system.png')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Frontend</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Tailwind component system</h2><p class="text-[#4b5563]">Reusable section elements with crisp responsive behaviour.</p></article>
    </section>
</div>
HTML;
    }

    private function showcaseProjectDetailContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-showcase-page capell-demo-showcase-page--project-detail mx-auto max-w-none p-0 text-[#111827]">
    <section class="capell-demo-showcase-article-layout mx-auto my-[clamp(4rem,8vw,8rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 items-start gap-[clamp(2rem,5vw,5rem)] lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.36fr)]">
        <article class="max-w-[46rem]">
            <p class="capell-demo-kicker">Project detail</p>
            <h1 class="max-w-[15ch] text-[clamp(3rem,8vw,6.5rem)] leading-[0.92] tracking-normal">Layout builder redesign for a flexible Capell website</h1>
            <p class="capell-demo-showcase-meta text-sm font-bold text-[#4b5563]">London | May 2026</p>
            <p class="max-w-[68ch] text-[#4b5563] leading-7">The project detail page shows the long-form content pattern: narrative copy, project metadata, team ownership, recent project links, and a promotion section in one structured layout.</p>
            <p class="max-w-[68ch] text-[#4b5563] leading-7">The implementation kept existing content intent but rebuilt the presentation around reusable Capell elements, asset-backed sections, and a clearer public route structure.</p>
            <blockquote class="my-8 border-l-4 border-[#c6923d] py-4 pl-6 text-[clamp(1.35rem,3vw,2rem)] font-extrabold leading-tight">“The real win is not visual novelty. It is being able to rebuild each section in the CMS without losing frontend discipline.”</blockquote>
        </article>
        <aside class="border border-[#d8dee8] bg-white p-6 shadow-[0_1rem_3rem_rgb(17_24_39_/_0.06)]">
            <h2>Project info</h2>
            <dl><div><dt>Client project</dt><dd>Capell demo estate</dd></div><div><dt>Project date</dt><dd>May 2026</dd></div><div><dt>Location</dt><dd>United Kingdom</dd></div></dl>
            <h2>Project head</h2>
            <p>Mara Ellison<br><span>CMS architecture lead</span></p>
        </aside>
    </section>
</div>
HTML;
    }

    private function showcaseBlogContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-showcase-page capell-demo-showcase-page--blog mx-auto max-w-none p-0 text-[#111827]">
    <section class="capell-demo-showcase-hero capell-demo-showcase-hero--compact relative isolate m-0 min-h-[min(64vh,40rem)] w-full max-w-none overflow-hidden bg-[linear-gradient(90deg,rgb(7_10_18_/_0.92),rgb(7_10_18_/_0.74),rgb(7_10_18_/_0.38)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center px-[max(1.5rem,calc((100vw_-_76rem)/2))] py-[clamp(4rem,8vw,8rem)] text-white">
        <p class="capell-demo-kicker">Latest news</p>
        <h1 class="max-w-[15ch] text-balance text-[clamp(3rem,8vw,6.5rem)] leading-[0.92] tracking-normal text-white">Our blog for Capell builders</h1>
        <p class="max-w-[42rem] text-white/80">Blog listings can use the same editorial rhythm as the rest of the site while staying powered by structured article content.</p>
    </section>
    <section class="capell-demo-showcase-blog-grid mx-auto my-[clamp(4rem,8vw,8rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 gap-4 md:grid-cols-3">
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.12),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-site-capture.png')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">News</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Home, buildings and architecture</h2><p class="text-[#4b5563]">How architecture-style page systems map to Capell layout builder websites.</p><a href="/home-buildings-and-architecture">Read more</a></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.1),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-installer.png')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Guide</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">Designing a better homepage flow</h2><p class="text-[#4b5563]">Turning mixed CMS objects into one coherent public page.</p><a href="/resources">Read more</a></article>
        <article class="min-h-60 border border-[#d8dee8] bg-white p-[clamp(1.25rem,3vw,2rem)] shadow-[0_1.25rem_3.5rem_rgb(17_24_39_/_0.06)]"><div class="mb-5 min-h-44 bg-[linear-gradient(135deg,rgb(7_10_18_/_0.1),rgb(7_10_18_/_0.5)),url('/images/capell-demo/capell-site-layout-example.jpeg')] bg-cover bg-center"></div><span class="text-sm font-bold uppercase text-[#4b5563]">Tips</span><h2 class="text-[clamp(1.4rem,3vw,2.4rem)] leading-none">How to avoid rigid templates</h2><p class="text-[#4b5563]">Use element boundaries, assets, and reusable sections to keep pages flexible.</p><a href="/resources">Read more</a></article>
    </section>
</div>
HTML;
    }

    private function showcaseSinglePostContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-showcase-page capell-demo-showcase-page--single-post mx-auto max-w-none p-0 text-[#111827]">
    <section class="capell-demo-showcase-article-layout mx-auto my-[clamp(4rem,8vw,8rem)] grid w-[min(76rem,calc(100%_-_3rem))] grid-cols-1 items-start gap-[clamp(2rem,5vw,5rem)] lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.36fr)]">
        <article class="max-w-[46rem]">
            <p class="capell-demo-kicker">Single post</p>
            <h1 class="max-w-[15ch] text-[clamp(3rem,8vw,6.5rem)] leading-[0.92] tracking-normal">Home, buildings and architecture</h1>
            <p class="capell-demo-showcase-meta text-sm font-bold text-[#4b5563]">By Capell Studio | May 16, 2026</p>
            <p class="max-w-[68ch] text-[#4b5563] leading-7">Architecture sites work because the page flow is deliberate: hero, proof, gallery, process, team, promotion, news, and contact. Capell can reproduce that structure with layout-builder elements instead of fixed page templates.</p>
            <p class="max-w-[68ch] text-[#4b5563] leading-7">The important point is ownership. Editors should be able to change content, order, media, navigation, and resource cards. Developers should still own rendering, performance, cache generation, and package boundaries.</p>
            <blockquote class="my-8 border-l-4 border-[#c6923d] py-4 pl-6 text-[clamp(1.35rem,3vw,2rem)] font-extrabold leading-tight">“A flexible CMS is not a free-for-all. It is a governed system with enough expressive range to build the whole site.”</blockquote>
            <p class="max-w-[68ch] text-[#4b5563] leading-7">This page closes the loop by proving the article detail template can sit beside the same showcase-inspired site map without copying the original design or losing the Capell product story.</p>
        </article>
        <aside class="grid gap-3 border border-[#d8dee8] bg-white p-6 shadow-[0_1rem_3rem_rgb(17_24_39_/_0.06)]">
            <h2>Recent posts</h2>
            <a href="/blog">Designing a better homepage flow</a>
            <a href="/blog">Avoiding rigid templates</a>
            <a href="/resources">Publishing checklist</a>
            <h2>Follow us</h2>
            <p>Resources, release notes, and implementation guides for Capell builders.</p>
        </aside>
    </section>
</div>
HTML;
    }

    private function pricingIndexContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-pricing-matrix capell-pricing-template">
    <section class="capell-pricing-hero">
        <p class="capell-pricing-eyebrow">Licensing &amp; technical support</p>
        <h1>Simple pricing for Capell CMS delivery</h1>
        <p>Choose the access and support model that fits your team. Start with a developer plan for evaluation, move to agency support for production delivery, or scope an enterprise agreement when governance and response times matter.</p>
        <div class="capell-pricing-actions">
            <a class="capell-pricing-button" href="/contact#scoping">Talk to sales</a>
            <a class="capell-pricing-button capell-pricing-button--secondary" href="/contact#scoping">Book pricing review</a>
        </div>
    </section>

    <section class="capell-pricing-matrix" aria-labelledby="capell-pricing-matrix-heading">
        <div class="capell-pricing-section-head">
            <p class="capell-pricing-eyebrow">Pricing matrix</p>
            <h2 id="capell-pricing-matrix-heading">Compare the commercial model</h2>
            <p>Every plan keeps the same Capell foundation. The difference is production usage, support access, and the level of commercial governance around your rollout.</p>
        </div>
        <div class="capell-pricing-plan-cards" aria-label="Pricing plans">
            <article>
                <span>Developer</span>
                <strong>GBP 0</strong>
                <p>For local evaluation and proof of concept work.</p>
                <ul>
                    <li>Core API access</li>
                    <li>Local projects</li>
                    <li>Community support</li>
                </ul>
                <a href="/contact#developer">Get started</a>
            </article>
            <article class="is-featured">
                <em>Popular</em>
                <span>Agency</span>
                <strong>GBP 99</strong>
                <p>For production projects with commercial support.</p>
                <ul>
                    <li>1 production project</li>
                    <li>Up to 5 custom domains</li>
                    <li>Email support</li>
                </ul>
                <a href="/contact#agency">Start trial</a>
            </article>
            <article>
                <span>Enterprise</span>
                <strong>Custom</strong>
                <p>For governed estates and dedicated support paths.</p>
                <ul>
                    <li>Unlimited projects</li>
                    <li>Unlimited custom domains</li>
                    <li>Dedicated support channel</li>
                </ul>
                <a href="/contact#enterprise">Contact sales</a>
            </article>
        </div>
        <div class="capell-pricing-table-wrap">
            <table class="capell-pricing-table">
                <thead>
                    <tr>
                        <th scope="col">Features</th>
                        <th scope="col">
                            <span>Developer</span>
                            <strong>GBP 0</strong>
                            <small>For local evaluation</small>
                            <a href="/contact#developer">Get started</a>
                        </th>
                        <th scope="col" class="is-featured">
                            <em>Popular</em>
                            <span>Agency</span>
                            <strong>GBP 99</strong>
                            <small>Per production project</small>
                            <a href="/contact#agency">Start trial</a>
                        </th>
                        <th scope="col">
                            <span>Enterprise</span>
                            <strong>Custom</strong>
                            <small>For governed estates</small>
                            <a href="/contact#enterprise">Contact sales</a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Core API access</th><td>Included</td><td>Included</td><td>Included</td></tr>
                    <tr><th scope="row">Production projects</th><td>Local only</td><td>1 project</td><td>Unlimited</td></tr>
                    <tr><th scope="row">Custom domains</th><td>-</td><td>Up to 5</td><td>Unlimited</td></tr>
                    <tr><th scope="row">Support tier</th><td>Community</td><td>Email support</td><td>Dedicated channel</td></tr>
                    <tr><th scope="row">SLA guidance</th><td>-</td><td>99.9%</td><td>99.99%</td></tr>
                </tbody>
            </table>
        </div>
        <p class="capell-pricing-note">Need custom implementation? View technical pricing for scoped migrations, frontend integration, and handover.</p>
    </section>

    <section class="capell-pricing-addons" aria-label="Support options">
        <article>
            <span>Support</span>
            <h2>Technical support</h2>
            <p>Access our dedicated support team for troubleshooting, architecture reviews, and emergency incident response.</p>
            <a href="/contact#support">Explore support plans</a>
        </article>
        <article>
            <span>Enablement</span>
            <h2>Team training</h2>
            <p>Onboard engineering and editorial teams faster with specialised workshops, certification paths, and deep technical sessions.</p>
            <a href="/training">View training catalogue</a>
        </article>
    </section>

    <section class="capell-pricing-faq" aria-labelledby="capell-pricing-faq-heading">
        <div class="capell-pricing-section-head">
            <p class="capell-pricing-eyebrow">Commercial questions</p>
            <h2 id="capell-pricing-faq-heading">Common pricing questions</h2>
        </div>
        <details>
            <summary>Can we start on Developer and move to Agency later?</summary>
            <p>Yes. Developer is intended for local evaluation and proof of concept work. Production projects should move to Agency or Enterprise before launch.</p>
        </details>
        <details>
            <summary>Is implementation included in the monthly licence?</summary>
            <p>No. Implementation is scoped separately so migration, theme integration, QA, and handover are priced around the actual delivery risk.</p>
        </details>
        <details>
            <summary>Do Enterprise plans include custom governance?</summary>
            <p>Yes. Enterprise plans can include security review, dedicated support paths, custom usage terms, and procurement documentation.</p>
        </details>
    </section>

    <section class="capell-pricing-cta">
        <div>
            <p class="capell-pricing-eyebrow">Next step</p>
            <h2>Ready for a technical deep dive?</h2>
            <p>Bring your content model, launch requirements, and support expectations. We will recommend the right plan and implementation route.</p>
        </div>
        <a class="capell-pricing-button" href="/contact#scoping">Book pricing review</a>
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
                <a class="capell-demo-button" href="/contact#scoping">Schedule scoping</a>
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

    private function complianceLocationContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-location-detail">
    <p>Compliance pages keep regional obligations, policy owners, review cadence, and evidence links close to the local publishing workflow.</p>
    <p>Use this child page to prove location content is structured, governed, and reusable instead of being hand-coded into a single landing page.</p>
</div>
HTML;
    }

    private function sustainabilityLocationContent(): string
    {
        return <<<'HTML'
<div class="capell-demo-page capell-demo-location-detail">
    <p>Sustainability pages give each region room to publish local initiatives while keeping measurement language, media, and taxonomy consistent.</p>
    <p>Editors can maintain local proof points without breaking the shared Capell page model or the wider site navigation.</p>
</div>
HTML;
    }

    private function integrationsIndexContent(): string
    {
        return $this->standardFooterPageContent('Integrations');
    }

    private function locationsIndexContent(): string
    {
        return $this->standardFooterPageContent('Locations');
    }

    private function partnersIndexContent(): string
    {
        return $this->standardFooterPageContent('Partners');
    }

    private function roadmapIndexContent(): string
    {
        return $this->standardFooterPageContent('Roadmap');
    }

    private function governanceIndexContent(): string
    {
        return $this->standardFooterPageContent('Governance');
    }

    private function trainingIndexContent(): string
    {
        return $this->standardFooterPageContent('Training');
    }

    private function standardFooterPageContent(string $pageName): string
    {
        $page = $this->standardFooterPageConfigs()[$pageName];
        $slug = Str::slug($pageName);

        return sprintf(
            <<<'HTML'
<div class="capell-demo-page capell-demo-footer-page capell-demo-footer-page--%1$s">
    <section class="capell-demo-footer-hero">
        <div class="capell-demo-footer-hero__copy">
            <p class="capell-demo-kicker">%2$s</p>
            <h1>%3$s</h1>
            <p>%4$s</p>
            <div class="capell-demo-actions">
                <a class="capell-demo-button" href="/contact#scoping">%5$s</a>
                <a class="capell-demo-button capell-demo-button--secondary" href="/resources">%6$s</a>
            </div>
        </div>
        <aside class="capell-demo-footer-artifact">
            <p class="capell-demo-brief__label">%7$s</p>
            <strong>%8$s</strong>
            <div class="capell-demo-footer-artifact__steps" aria-hidden="true">%10$s</div>
        </aside>
    </section>

    <nav class="capell-demo-footer-tabs" aria-label="Footer page variations">
        %11$s
    </nav>

    <section class="capell-demo-footer-editorial">
        <div class="capell-demo-footer-editorial__copy">
            <p class="capell-demo-kicker">Shared layout system</p>
            <h2>%12$s</h2>
            <p>%13$s</p>
            <p>%14$s</p>
            <p>%17$s</p>
        </div>
        <aside class="capell-demo-footer-proof">
            <span>%15$s</span>
            <strong>%16$s</strong>
            <dl>%9$s</dl>
        </aside>
    </section>

    <section class="capell-demo-footer-section">
        <div class="capell-demo-footer-section-head">
            <p class="capell-demo-kicker">%2$s content modules</p>
            <h2>Reusable sections with page-specific assets and deeper copy</h2>
            <p>The layout stays consistent across footer pages, but the evidence, examples, labels, metrics, and calls to action change enough that each page feels intentionally written.</p>
        </div>
        <div class="capell-demo-footer-sections" aria-label="%2$s capabilities">%18$s</div>
    </section>

    <section class="capell-demo-footer-evidence">
        <div class="capell-demo-footer-section-head">
            <p class="capell-demo-kicker">%19$s</p>
            <h2>%20$s</h2>
            <p>%21$s</p>
        </div>
        <div class="capell-demo-footer-evidence__rows">%22$s</div>
    </section>

    <section class="capell-demo-footer-variations" aria-label="Reusable asset slots">
        <div class="capell-demo-footer-section-head">
            <p class="capell-demo-kicker">Asset slots</p>
            <h2>Same elements, different page assets</h2>
            <p>Each footer page uses the same shell, then swaps the hero metric, proof list, feature modules, evidence rows, testimonial, and CTA details.</p>
        </div>
        <div class="capell-demo-footer-variation-strip">%23$s</div>
    </section>

    <section class="capell-demo-footer-cta">
        <div>
            <p class="capell-demo-kicker">%24$s</p>
            <h2>%25$s</h2>
            <p>%26$s</p>
        </div>
        <a class="capell-demo-button" href="/contact#scoping">%27$s</a>
    </section>
</div>
HTML,
            $slug,
            $pageName,
            $page['headline'],
            $page['intro'],
            $page['primaryAction'],
            $page['secondaryAction'],
            $page['assetLabel'],
            $page['assetMetric'],
            $this->standardFooterDefinitionList($page['stats']),
            $this->standardFooterVisual($page['visual']),
            $this->standardFooterPageTabs($pageName),
            $page['storyHeading'],
            $page['storyLead'],
            $page['storyDetail'],
            $page['proofLabel'],
            $page['proofTitle'],
            $page['proofBody'],
            $this->standardFooterFeatureCards($pageName, $page['features']),
            $page['deepDiveKicker'],
            $page['deepDiveHeading'],
            $page['deepDiveIntro'],
            $this->standardFooterRows($page['rows']),
            $this->standardFooterAssetSlots($page['assetSlots']),
            $page['ctaKicker'],
            $page['ctaHeading'],
            $page['ctaBody'],
            $page['ctaAction'],
        );
    }

    /**
     * @param  array<string, string>  $items
     */
    private function standardFooterDefinitionList(array $items): string
    {
        return implode('', array_map(
            fn (string $label, string $value): string => sprintf('<div><dt>%s</dt><dd>%s</dd></div>', $label, $value),
            array_keys($items),
            $items,
        ));
    }

    /**
     * @param  list<string>  $items
     */
    private function standardFooterVisual(array $items): string
    {
        return implode('', array_map(
            fn (string $item, int $index): string => sprintf('<div><span>%02d</span><strong>%s</strong></div>', $index + 1, $item),
            $items,
            array_keys($items),
        ));
    }

    private function standardFooterPageTabs(string $activeName): string
    {
        return implode('', array_map(
            function (string $name, array $page) use ($activeName): string {
                $current = $name === $activeName ? ' aria-current="page"' : '';
                $activeClass = $name === $activeName ? ' is-active' : '';

                return sprintf(
                    '<a class="capell-demo-footer-tabs__item%1$s" href="/%7$s"%2$s><span>%3$s</span><strong>%4$s</strong><em>%6$s</em><p>%5$s</p></a>',
                    $activeClass,
                    $current,
                    $page['tabCode'],
                    $name,
                    $page['tabCopy'],
                    $page['tabMetric'],
                    Str::slug($name),
                );
            },
            array_keys($this->standardFooterPageConfigs()),
            $this->standardFooterPageConfigs(),
        ));
    }

    /**
     * @param  list<array{label: string, title: string, body: string}>  $features
     */
    private function standardFooterFeatureCards(string $pageName, array $features): string
    {
        $details = $this->standardFooterFeatureDetails($pageName);

        return implode('', array_map(
            fn (array $feature, int $index): string => sprintf(
                '<article><span>%s</span><h2>%s</h2><p>%s</p><p>%s</p></article>',
                $feature['label'],
                $feature['title'],
                $feature['body'],
                $details[$index],
            ),
            $features,
            array_keys($features),
        ));
    }

    /**
     * @param  list<array{label: string, title: string, body: string}>  $rows
     */
    private function standardFooterRows(array $rows): string
    {
        return implode('', array_map(
            fn (array $row, int $index): string => sprintf(
                '<article><span>%02d</span><div><em>%s</em><strong>%s</strong></div><p>%s</p></article>',
                $index + 1,
                $row['label'],
                $row['title'],
                $row['body'],
            ),
            $rows,
            array_keys($rows),
        ));
    }

    /**
     * @param  array<string, string>  $slots
     */
    private function standardFooterAssetSlots(array $slots): string
    {
        return implode('', array_map(
            fn (string $label, string $value): string => sprintf('<div><span>%s</span><strong>%s</strong></div>', $label, $value),
            array_keys($slots),
            $slots,
        ));
    }

    /**
     * @return list<string>
     */
    private function standardFooterFeatureDetails(string $pageName): array
    {
        return match ($pageName) {
            'Integrations' => [
                'Use the module for system diagrams, payload notes, authentication expectations, and support ownership so commercial readers understand the integration shape without needing a developer-only reference page.',
                'Package and marketplace references can sit beside customer-facing value, making the page feel specific to Capell while keeping implementation details safely out of public templates.',
                'Status, retry, and audit details turn the page into a credibility surface. The content can show how operators recover from failed syncs instead of only saying that integrations exist.',
            ],
            'Locations' => [
                'The page can explain how local branches, child pages, translated paths, and media ownership work together without forcing every region into identical copy.',
                'Regional examples, search metadata, and ownership notes give visitors useful local context while still showing that the CMS model is shared and maintainable.',
                'Publishing discipline remains visible through cache coverage, redirects, translations, and editorial boundaries, which matters once the location network grows beyond a handful of pages.',
            ],
            'Partners' => [
                'Certification criteria, architecture review, and handover expectations can be written into the page so partnership feels like a delivery model rather than a directory.',
                'Runbooks, demo assets, and package guidance give partner teams material they can reuse, while the public page still stays easy for prospects to scan.',
                'Extension boundaries keep the ecosystem credible. Partners can see where they add value without implying they own the core product contract.',
            ],
            'Roadmap' => [
                'Release lanes help readers distinguish committed work from research, which makes the roadmap more trustworthy than a flat list of ambitions.',
                'Feedback prompts can ask for the right evidence at the right point, so customer signal becomes part of the page rather than a separate product-board ritual.',
                'Changelog links, shipped examples, and confidence labels keep older roadmap content honest by making it obvious what moved from promise to production.',
            ],
            'Governance' => [
                'Workflow gates can be described in public language while the admin mechanics stay private, giving buyers confidence without exposing internal control details.',
                'Audit references, reviewer roles, and publish evidence show that governance is part of the operating model, not a compliance paragraph added after the fact.',
                'Role-aware copy helps separate editor permissions, emergency access, and preview behaviour from the public page output that visitors actually receive.',
            ],
            'Training' => [
                'Editor, developer, and owner paths can each get their own module so training reads like a real handover plan instead of a generic onboarding promise.',
                'Runbook assets make the page useful after launch because the same structure can point teams back to deployment, cache, package, and support routines.',
                'Readiness checks, rehearsals, and module completion give the training page proof that the team can operate Capell once implementation support steps back.',
            ],
            default => ['', '', ''],
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function standardFooterPageConfigs(): array
    {
        return [
            'Integrations' => [
                'tabCode' => 'API',
                'tabCopy' => 'Connectors, webhooks, and extension health.',
                'tabMetric' => '24 endpoints',
                'headline' => 'Integration surfaces for teams that need traceable sync',
                'intro' => 'Show the connector story with a shared editorial layout, then swap in API diagrams, webhook states, marketplace extension proof, and operational sync metrics.',
                'primaryAction' => 'Plan an integration',
                'secondaryAction' => 'Read API notes',
                'assetLabel' => 'Connector map',
                'assetMetric' => '24 monitored touchpoints',
                'stats' => ['Surface' => 'Marketplace extensions, webhooks, API tokens', 'Reliability' => 'Retries, audit entries, health alerts', 'Owner' => 'Developer-led setup with editor-safe status'],
                'visual' => ['Register connector', 'Map payload', 'Observe sync', 'Review failures'],
                'storyHeading' => 'Make integrations feel operational, not decorative',
                'storyLead' => 'The same footer-page shell can explain API work without becoming a docs page. The hero asset becomes a connector map, the proof panel becomes health telemetry, and the deep-dive rows become lifecycle steps.',
                'storyDetail' => 'That keeps the public page persuasive for buyers while still signalling that Capell integrations are Laravel-native, observable, and owned by packages.',
                'proofLabel' => 'Operational proof',
                'proofTitle' => 'Every connector needs an owner, a retry path, and a public explanation.',
                'proofBody' => 'The page assets show which systems connect, what Capell records, and how teams recover when a sync fails.',
                'features' => [
                    ['label' => 'Connect', 'title' => 'API and webhook entry points', 'body' => 'Describe inbound submissions, outbound notifications, and token-controlled integration access.'],
                    ['label' => 'Extend', 'title' => 'Marketplace package surface', 'body' => 'Show how extension installs and health alerts sit beside the core CMS rather than inside page templates.'],
                    ['label' => 'Observe', 'title' => 'Sync confidence signals', 'body' => 'Use health checks, recent events, and retry notes as assets that change per integration.'],
                ],
                'deepDiveKicker' => 'Lifecycle',
                'deepDiveHeading' => 'From connector request to monitored production sync',
                'deepDiveIntro' => 'The table module becomes an integration lifecycle without changing the surrounding layout.',
                'rows' => [
                    ['label' => 'Discovery', 'title' => 'Map source systems', 'body' => 'Confirm records, identifiers, auth flow, expected volume, and failure ownership.'],
                    ['label' => 'Build', 'title' => 'Wire package-owned actions', 'body' => 'Keep connector logic in actions and package services, not in public content output.'],
                    ['label' => 'Verify', 'title' => 'Publish health evidence', 'body' => 'Surface connection state, last sync, retry policy, and escalation paths for operators.'],
                ],
                'assetSlots' => ['Hero metric' => '24 touchpoints', 'Proof list' => 'Retries and health alerts', 'Feature cards' => 'API, extensions, observability', 'Process rows' => 'Discover, build, verify', 'Testimonial' => 'Developer owner quote', 'CTA' => 'Plan an integration'],
                'ctaKicker' => 'Integration planning',
                'ctaHeading' => 'Turn fragile sync work into an owned Capell surface',
                'ctaBody' => 'Bring the systems, auth model, and failure rules. The implementation can stay boring when the ownership is clear.',
                'ctaAction' => 'Start integration scoping',
            ],
            'Locations' => [
                'tabCode' => 'LOC',
                'tabCopy' => 'Regional page trees and local ownership.',
                'tabMetric' => '18 regions',
                'headline' => 'Multi-site delivery without losing local context',
                'intro' => 'Use location pages to show regional landing pages, local governance, routed child content, and shared CMS operations from one Laravel install.',
                'primaryAction' => 'Plan a rollout',
                'secondaryAction' => 'Read migration notes',
                'assetLabel' => 'Network signal',
                'assetMetric' => '18 publishable regions',
                'stats' => ['Site model' => 'One CMS, many domains, shared package rules', 'Local ownership' => 'Regional editors with clear boundaries', 'Launch priority' => 'Redirects, translated slugs, media, cache coverage'],
                'visual' => ['Global model', 'Regional tree', 'Local proof', 'Shared cache'],
                'storyHeading' => 'Let every region vary without forking the system',
                'storyLead' => 'The shared footer layout becomes a location hub by swapping in a coverage map, regional proof list, and local publishing cards.',
                'storyDetail' => 'Visitors see a tailored local story while operators see the bigger point: Capell can run many page trees through one admin model.',
                'proofLabel' => 'Operational proof',
                'proofTitle' => 'Local pages should prove structure, not just list addresses.',
                'proofBody' => 'The assets show regional ownership, routed child content, translated slugs, and shared infrastructure.',
                'features' => [
                    ['label' => 'Publish', 'title' => 'Regional page trees', 'body' => 'Give each location its own content branch while keeping reusable sections and global rules consistent.'],
                    ['label' => 'Localize', 'title' => 'Language and slug control', 'body' => 'Use translation records and explicit paths for local search and editor confidence.'],
                    ['label' => 'Govern', 'title' => 'Shared release discipline', 'body' => 'Keep redirects, media, cache generation, and page discovery in one operational workflow.'],
                ],
                'deepDiveKicker' => 'Coverage model',
                'deepDiveHeading' => 'A repeatable path for location networks',
                'deepDiveIntro' => 'The deep-dive module becomes a regional rollout ledger.',
                'rows' => [
                    ['label' => 'Model', 'title' => 'Define the region taxonomy', 'body' => 'Capture locations, service areas, languages, ownership, and page relationships.'],
                    ['label' => 'Migrate', 'title' => 'Move local content safely', 'body' => 'Preserve media, redirects, search metadata, and legacy URL confidence.'],
                    ['label' => 'Operate', 'title' => 'Give editors local guardrails', 'body' => 'Allow local updates while shared layouts, cache, and publishing checks stay central.'],
                ],
                'assetSlots' => ['Hero metric' => '18 regions', 'Proof list' => 'Ownership and routing', 'Feature cards' => 'Publish, localize, govern', 'Process rows' => 'Model, migrate, operate', 'Testimonial' => 'Regional editor quote', 'CTA' => 'Plan a rollout'],
                'ctaKicker' => 'Location rollout',
                'ctaHeading' => 'Make local pages manageable before the network grows',
                'ctaBody' => 'Bring the regions, languages, legacy paths, and ownership model. Capell can keep the rollout structured from the first page.',
                'ctaAction' => 'Scope location pages',
            ],
            'Partners' => [
                'tabCode' => 'PRT',
                'tabCopy' => 'Certified delivery and extension partners.',
                'tabMetric' => '4 tiers',
                'headline' => 'Partner delivery paths with clear implementation boundaries',
                'intro' => 'Show agencies, integration partners, and extension builders how Capell supports co-delivery without blurring who owns architecture, code, content, and launch.',
                'primaryAction' => 'Discuss partnership',
                'secondaryAction' => 'Read partner notes',
                'assetLabel' => 'Partner ladder',
                'assetMetric' => '4 enablement tiers',
                'stats' => ['Partner types' => 'Agencies, implementers, extension builders', 'Enablement' => 'Runbooks, demos, architecture review', 'Delivery rule' => 'Named owner for every workstream'],
                'visual' => ['Evaluate fit', 'Certify workflow', 'Co-deliver', 'Support launch'],
                'storyHeading' => 'Show partnership as a delivery system',
                'storyLead' => 'The same layout becomes a partner page by replacing product proof with enablement assets: tiers, review lanes, co-delivery roles, and extension ownership.',
                'storyDetail' => 'The result reads like a serious implementation network rather than a logo wall.',
                'proofLabel' => 'Partner proof',
                'proofTitle' => 'A good partner page explains how work gets handed off.',
                'proofBody' => 'Use the asset panel to show tier, review cadence, supported packages, and expected launch ownership.',
                'features' => [
                    ['label' => 'Certify', 'title' => 'Implementation standards', 'body' => 'Make architecture review, testing, cache checks, and editor handover part of the partner promise.'],
                    ['label' => 'Enable', 'title' => 'Reusable partner assets', 'body' => 'Give partners demo pages, runbooks, migration notes, and package guidance they can use repeatedly.'],
                    ['label' => 'Extend', 'title' => 'Package ecosystem fit', 'body' => 'Show where partners can build extensions without taking over the core CMS contract.'],
                ],
                'deepDiveKicker' => 'Enablement',
                'deepDiveHeading' => 'A partner lane for each delivery shape',
                'deepDiveIntro' => 'The table module becomes a partner tier model.',
                'rows' => [
                    ['label' => 'Referral', 'title' => 'Qualified introduction', 'body' => 'The partner receives project context, timing, and the Capell fit before scoping.'],
                    ['label' => 'Certified', 'title' => 'Reviewed implementation', 'body' => 'Architecture and launch checks are reviewed against Capell standards.'],
                    ['label' => 'Extension', 'title' => 'Package-owned delivery', 'body' => 'Partner code ships as a package surface with documentation and upgrade boundaries.'],
                ],
                'assetSlots' => ['Hero metric' => '4 tiers', 'Proof list' => 'Standards and review', 'Feature cards' => 'Certify, enable, extend', 'Process rows' => 'Referral, certified, extension', 'Testimonial' => 'Partner lead quote', 'CTA' => 'Discuss partnership'],
                'ctaKicker' => 'Partner program',
                'ctaHeading' => 'Make co-delivery legible before clients enter the process',
                'ctaBody' => 'Define the partner lane, package surface, and review rules so delivery stays clear.',
                'ctaAction' => 'Start partner scoping',
            ],
            'Roadmap' => [
                'tabCode' => 'MAP',
                'tabCopy' => 'Release lanes, voting, and changelog proof.',
                'tabMetric' => '6 lanes',
                'headline' => 'A roadmap page that turns product direction into trust',
                'intro' => 'Use the shared page system to show what is planned, what is shipping, what is open for feedback, and how releases are governed.',
                'primaryAction' => 'Share feedback',
                'secondaryAction' => 'Read release notes',
                'assetLabel' => 'Release board',
                'assetMetric' => '6 active lanes',
                'stats' => ['Planning' => 'Now, next, later, research', 'Feedback' => 'Votes, comments, customer context', 'Confidence' => 'Changelog links and shipped proof'],
                'visual' => ['Collect signal', 'Prioritize lane', 'Ship release', 'Close loop'],
                'storyHeading' => 'Roadmaps need confidence, not a wish list',
                'storyLead' => 'The reusable layout becomes a roadmap by swapping the proof panel for release lanes, vote counts, confidence labels, and shipped examples.',
                'storyDetail' => 'It gives buyers and builders a reliable product-direction page without inventing a separate design pattern.',
                'proofLabel' => 'Roadmap proof',
                'proofTitle' => 'Every roadmap item should say why it matters and where it sits.',
                'proofBody' => 'Use page assets for lane status, release confidence, and feedback prompts.',
                'features' => [
                    ['label' => 'Prioritize', 'title' => 'Release lanes', 'body' => 'Group work into now, next, later, research, partner, and platform lanes.'],
                    ['label' => 'Listen', 'title' => 'Feedback loops', 'body' => 'Show which items accept input and what kind of customer evidence helps.'],
                    ['label' => 'Prove', 'title' => 'Changelog confidence', 'body' => 'Link shipped work back to roadmap promises so the page stays credible.'],
                ],
                'deepDiveKicker' => 'Release lanes',
                'deepDiveHeading' => 'A roadmap module that can become a public operating rhythm',
                'deepDiveIntro' => 'The table module becomes a release lane tracker.',
                'rows' => [
                    ['label' => 'Now', 'title' => 'Committed delivery', 'body' => 'Work with owner, scope, acceptance criteria, and expected release window.'],
                    ['label' => 'Next', 'title' => 'Validated direction', 'body' => 'High-confidence work waiting for final sequencing and implementation capacity.'],
                    ['label' => 'Research', 'title' => 'Open product questions', 'body' => 'Ideas that need customer context before they become promises.'],
                ],
                'assetSlots' => ['Hero metric' => '6 lanes', 'Proof list' => 'Votes and confidence', 'Feature cards' => 'Prioritize, listen, prove', 'Process rows' => 'Now, next, research', 'Testimonial' => 'Product owner quote', 'CTA' => 'Share feedback'],
                'ctaKicker' => 'Product direction',
                'ctaHeading' => 'Show what is next without overpromising',
                'ctaBody' => 'Use roadmap assets to make product direction specific, inspectable, and easier to trust.',
                'ctaAction' => 'Discuss roadmap needs',
            ],
            'Governance' => [
                'tabCode' => 'GOV',
                'tabCopy' => 'Approvals, audit logs, roles, and policy checks.',
                'tabMetric' => '12 controls',
                'headline' => 'Governance content for teams that publish with consequences',
                'intro' => 'Use the shared footer layout to explain approvals, permissions, audit history, compliance readiness, and release control without turning the page into a legal document.',
                'primaryAction' => 'Review governance',
                'secondaryAction' => 'Read workflow notes',
                'assetLabel' => 'Control panel',
                'assetMetric' => '12 publishing controls',
                'stats' => ['Workflow' => 'Draft, review, approve, publish', 'Traceability' => 'Audit logs and workspace comments', 'Access' => 'Roles, gates, and break-glass paths'],
                'visual' => ['Draft change', 'Review owner', 'Approve release', 'Audit event'],
                'storyHeading' => 'Governance should be visible before it is needed',
                'storyLead' => 'The reusable shell becomes a governance page by swapping the hero asset for controls, proof rows for audit signals, and feature cards for publishing safety.',
                'storyDetail' => 'This gives technical buyers confidence that Capell can support structured review without leaking admin workflow into public pages.',
                'proofLabel' => 'Control proof',
                'proofTitle' => 'Publishing confidence depends on roles, evidence, and recovery paths.',
                'proofBody' => 'Use assets to show reviewers, audit entries, approval state, and escalation paths.',
                'features' => [
                    ['label' => 'Approve', 'title' => 'Workflow gates', 'body' => 'Make review, approval, and scheduled publish steps explicit for content teams.'],
                    ['label' => 'Trace', 'title' => 'Audit-ready changes', 'body' => 'Show who changed what, when it moved, and how release owners verify it.'],
                    ['label' => 'Protect', 'title' => 'Role-aware access', 'body' => 'Keep admin rights, preview links, and emergency controls separate from public rendering.'],
                ],
                'deepDiveKicker' => 'Control model',
                'deepDiveHeading' => 'Governance checks that map to real publishing work',
                'deepDiveIntro' => 'The table module becomes an approval and audit checklist.',
                'rows' => [
                    ['label' => 'Access', 'title' => 'Define role boundaries', 'body' => 'Name who can edit, review, publish, grant access, and recover during incidents.'],
                    ['label' => 'Workflow', 'title' => 'Model approval gates', 'body' => 'Represent draft, review, approval, and publish states without exposing admin details publicly.'],
                    ['label' => 'Evidence', 'title' => 'Keep audit trails useful', 'body' => 'Capture workspace comments, review assignments, publish events, and release evidence.'],
                ],
                'assetSlots' => ['Hero metric' => '12 controls', 'Proof list' => 'Roles and audit logs', 'Feature cards' => 'Approve, trace, protect', 'Process rows' => 'Access, workflow, evidence', 'Testimonial' => 'Release lead quote', 'CTA' => 'Review governance'],
                'ctaKicker' => 'Publishing control',
                'ctaHeading' => 'Treat governance as a product surface, not a footnote',
                'ctaBody' => 'Bring your roles, review model, and compliance pressure. Capell can make the workflow legible.',
                'ctaAction' => 'Scope governance',
            ],
            'Training' => [
                'tabCode' => 'TRN',
                'tabCopy' => 'Editor onboarding and developer runbooks.',
                'tabMetric' => '9 modules',
                'headline' => 'Training pages that help teams actually own the CMS',
                'intro' => 'Use the same content skeleton to show editor onboarding, developer runbooks, workshop formats, launch handover, and operational confidence.',
                'primaryAction' => 'Plan training',
                'secondaryAction' => 'Read launch guides',
                'assetLabel' => 'Training map',
                'assetMetric' => '9 handover modules',
                'stats' => ['Audience' => 'Editors, developers, release owners', 'Format' => 'Workshops, runbooks, launch checklists', 'Outcome' => 'Confident ownership after handover'],
                'visual' => ['Orient team', 'Practice workflow', 'Document runbook', 'Review launch'],
                'storyHeading' => 'Handover is part of the product experience',
                'storyLead' => 'The shared footer layout becomes training-focused by replacing the proof assets with modules, exercises, readiness checks, and owner-specific paths.',
                'storyDetail' => 'It shows that Capell is not just installed. It is handed over in a way teams can operate.',
                'proofLabel' => 'Readiness proof',
                'proofTitle' => 'Training should leave evidence that the team can run the CMS.',
                'proofBody' => 'Use assets for module completion, runbook ownership, launch rehearsal, and editor confidence.',
                'features' => [
                    ['label' => 'Onboard', 'title' => 'Editor workflow training', 'body' => 'Teach content owners how pages, media, previews, and approvals fit together.'],
                    ['label' => 'Document', 'title' => 'Developer runbooks', 'body' => 'Give developers package, cache, deployment, and troubleshooting notes they can trust.'],
                    ['label' => 'Rehearse', 'title' => 'Launch enablement', 'body' => 'Practice publish, rollback, cache, search, and support workflows before go-live.'],
                ],
                'deepDiveKicker' => 'Handover',
                'deepDiveHeading' => 'A training module for each ownership group',
                'deepDiveIntro' => 'The table module becomes a practical curriculum.',
                'rows' => [
                    ['label' => 'Editors', 'title' => 'Publishing workflow', 'body' => 'Pages, sections, media, SEO fields, previews, and approval expectations.'],
                    ['label' => 'Developers', 'title' => 'Operational runbook', 'body' => 'Package updates, assets, cache generation, diagnostics, and extension boundaries.'],
                    ['label' => 'Owners', 'title' => 'Launch readiness', 'body' => 'Handover checks, support model, release calendar, and escalation paths.'],
                ],
                'assetSlots' => ['Hero metric' => '9 modules', 'Proof list' => 'Readiness checks', 'Feature cards' => 'Onboard, document, rehearse', 'Process rows' => 'Editors, developers, owners', 'Testimonial' => 'Editor owner quote', 'CTA' => 'Plan training'],
                'ctaKicker' => 'Training and handover',
                'ctaHeading' => 'Make CMS ownership explicit before launch day',
                'ctaBody' => 'Bring the team structure, release responsibilities, and support model. Training should map to real ownership.',
                'ctaAction' => 'Scope training',
            ],
        ];
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

    private function createWidgetMedia(Element $model, ?string $name = null, string $type = 'image', BackedEnum|string $collection = MediaCollectionEnum::Image): Media
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

        // Create content and link via ElementAsset
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
