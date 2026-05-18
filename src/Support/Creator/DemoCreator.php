<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

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
use Capell\LayoutBuilder\Models\Block;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;

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
        $this->blockModel = Block::class;
        $this->contentModel = CapellCore::hasAsset('Section')
            ? CapellCore::getAsset('Section')->model
            : Page::class;
    }

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
}
