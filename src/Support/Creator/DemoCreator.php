<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Contracts\PageCreatable;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\PageCreator;
use Capell\DemoKit\Actions\DummyContentGeneratorAction;
use Capell\DemoKit\Support\DemoContentPool;
use Capell\LayoutBuilder\Models\Widget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;

class DemoCreator extends ApDemoBlockCreator
{
    use Macroable;

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
        $this->blockModel = Widget::class;
        $contentModel = CapellCore::hasAsset('Section')
            ? CapellCore::getAsset('Section')->model
            : Page::class;

        throw_unless(
            is_subclass_of($contentModel, Model::class) && is_subclass_of($contentModel, HasMedia::class),
            InvalidArgumentException::class,
            'Demo content model must be an Eloquent media model.',
        );

        /** @var class-string<Model&HasMedia> $contentModel */
        $this->contentModel = $contentModel;
    }

    /**
     * @param  Collection<int, Language>|null  $languages
     */
    public function setupSite(Site $site, ?Collection $languages = null): void
    {
        $languages ??= Language::query()
            ->whereKey($site->languages->modelKeys())
            ->get();

        $title = ctype_digit($site->name[0]) ? $site->name : Str::title($site->name);

        $meta = [
            'brand_color' => null,
            'contact_page_id' => null,
            'favicon' => null,
            'icon' => null,
            'meta_schema' => [],
            'twitter' => null,
            ...($site->meta ?? []),
        ];

        $meta['business_name'] = $title . ' ltd';
        $meta['email'] = config('mail.from.address');
        $meta['phone'] = '0123456789';
        $meta['footer_content'] = 'Footer content here';
        $meta['social_links'] = [
            [
                'type' => 'facebook',
                'url' => 'https://facebook.com',
                'icon' => 'fab-square-facebook',
                'title' => null,
            ],
            [
                'type' => 'twitter',
                'url' => 'https://twitter.com',
                'icon' => 'fab-square-x-twitter',
                'title' => null,
            ],
            [
                'type' => 'instagram',
                'url' => 'https://instagram.com',
                'icon' => 'fab-square-instagram',
                'title' => null,
            ],
        ];

        $site->update(['meta' => $meta]);

        foreach ($languages as $language) {
            $site->translations()->updateOrCreate(['language_id' => $language->id], [
                'title' => $title,
                'meta' => [
                    'title_after_text' => null,
                    'description' => 'Description for ' . $title,
                    'footer_copy' => sprintf('<p>&copy; :year %s</p>', $title),
                    'label' => null,
                    'ai_discovery' => [
                        'llms_txt_enabled' => false,
                        'llms_full_txt_enabled' => false,
                        'markdown_pages_enabled' => false,
                        'accept_markdown_enabled' => false,
                        'default_include_pages' => false,
                        'status' => null,
                        'default_section' => null,
                        'max_full_txt_pages' => null,
                        'max_full_txt_bytes' => null,
                        'cache_ttl_seconds' => null,
                        'intro_markdown' => null,
                    ],
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

    /**
     * @param  array<array-key, mixed>  $languages
     */
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
     * @param  array<array-key, mixed>  $data
     * @param  Collection<int, Language>|null  $languages
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

        $names = is_array($data['name'] ?? null) ? $data['name'] : [];
        $name = $this->canonicalDemoPageName(Str::title($this->preferredTranslatedValue($names, $languages)));
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
            'visible_from' => now()->subDays(random_int(0, 90))->format('Y-m-d'),
        ];

        if ($parent instanceof Pageable) {
            $pageData['parent_id'] = $parent->getKey();
        }

        $languages->each(function (Language $language) use (&$pageData, $name, $data): void {
            $localizedName = $data['name'][$language->code] ?? $name;
            $title = Str::title($this->canonicalDemoPageName($localizedName));

            $slug = Str::slug($title);

            $desc_content = $this->demoPageContent($name, $language->code)
                ?? DummyContentGeneratorAction::run($language->code);

            $pageData['translations'][$language->code] = [
                'title' => $title,
                'content' => $desc_content,
                'summary' => $this->demoPageSummary($name),
                'meta' => [
                    'description' => str($desc_content)->stripTags()->limit(160),
                    'hero' => $this->demoPageHeroContent($name, $desc_content),
                    'hero_title' => $title,
                    'keywords' => implode(',', array_slice(explode(' ', $title), 0, 10)),
                    'label' => $title,
                    'link_text' => $this->randomItem([
                        'Learn More',
                        'Read More',
                        'Get Started',
                        'More information',
                        'View the full notes',
                    ]),
                    'slug' => $slug,
                ],
            ];
        });

        $page = $pageCreator->createPage($pageData, $site, $languages);

        if ($createMedia && $page instanceof Model) {
            $this->createMedia($page, $name);
        }

        if ($page instanceof Page) {
            $this->syncDemoPageContentAssets($page, $name);
        }

        return $page;
    }

    /**
     * @param  Collection<int, Language>  $languages
     */
    public function refreshDemoPage(Page $page, Collection $languages, bool $refreshUrls = true): Page
    {
        $name = $this->canonicalDemoPageName($page->name);
        $layout = $this->layoutForDemoPage($name);

        if (! $layout instanceof Layout) {
            throw new InvalidArgumentException(sprintf('Unable to resolve a demo layout for [%s].', $name));
        }

        if ($name === 'Contact') {
            $site = $page->site;

            throw_unless($site instanceof Site, InvalidArgumentException::class, 'Unable to resolve a site for the contact demo page.');

            $this->ensureContactFormIntegration($site);
        }

        $page->forceFill([
            'name' => $name,
            'layout_id' => $layout->getKey(),
            'meta' => $this->demoPageMeta($name),
            'visible_from' => $page->visible_from ?? now()->subDay()->format('Y-m-d'),
        ])->save();

        $languages->each(function (Language $language) use ($page, $name): void {
            $title = Str::title($name);
            $content = $this->demoPageContent($name, $language->code)
                ?? DummyContentGeneratorAction::run($language->code);

            $translation = $page->translations()->firstOrNew(['language_id' => $language->getKey()]);
            $translationPayload = [
                'title' => $title,
                'content' => $content,
                'meta' => [
                    'description' => str($content)->stripTags()->limit(160),
                    'hero' => $this->demoPageHeroContent($name, $content),
                    'hero_title' => $title,
                    'keywords' => implode(',', array_slice(explode(' ', $title), 0, 10)),
                    'label' => $title,
                    'link_text' => 'Learn More',
                    'slug' => Str::slug($title),
                ],
            ];

            if (Schema::hasColumn($translation->getTable(), 'summary')) {
                $translationPayload['summary'] = $this->demoPageSummary($name);
            }

            $translation->forceFill($translationPayload)->save();
        });

        if ($refreshUrls) {
            SetupPageUrlsAction::run($page);
        }

        $this->syncDemoPageContentAssets($page, $name);

        return $page->refresh();
    }

    public function setupRelatedSites(): void
    {
        $sites = $this->siteModel::with(['language', 'translations'])->get();
        $defaultSite = $this->siteModel::getDefault();

        throw_unless($defaultSite instanceof Site, InvalidArgumentException::class, 'Unable to resolve the default site for related site setup.');

        $this->attachRelatedSites($defaultSite, $sites);

        $sites->each(function (Site $site): void {
            $relatedSites = $this->findRelatedSites($site);

            $site->related()->attach($relatedSites)->save();
        });
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  Collection<int, Language>  $languages
     */
    private function preferredTranslatedValue(array $values, Collection $languages): string
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

        throw new InvalidArgumentException('Demo page data must include at least one translated name.');
    }
}
