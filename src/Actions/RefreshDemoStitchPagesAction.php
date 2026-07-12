<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * @method static Collection<int, Page> run(?string $siteName = null, ?string $languageCode = null, bool $force = false)
 */
final class RefreshDemoStitchPagesAction
{
    use AsObject;

    /** @var list<string> */
    private const array RootPageNames = [
        'Contact',
        'Pricing',
        'Resources',
        'About Us',
        'Homepage 2',
        'Services',
        'Team',
        'FAQ',
        'Testimonials',
        'Projects',
        'Project Detail',
        'Blog',
        'Home, Buildings and Architecture',
        'Platform Architecture',
        'Integrations',
        'Locations',
        'Partners',
        'Roadmap',
        'Governance',
        'Training',
    ];

    /** @var array<string, string> */
    private const array ChildPageParents = [
        'Implementation' => 'Pricing',
        'Compliance' => 'Locations',
        'Sustainability' => 'Locations',
    ];

    /**
     * @return Collection<int, Page>
     */
    public function handle(?string $siteName = null, ?string $languageCode = null, bool $force = false): Collection
    {
        throw_unless($force, InvalidArgumentException::class, 'Refreshing Stitch demo pages requires force confirmation.');

        $sites = $this->sites($siteName);
        $refreshedPages = collect();

        $sites->each(function (Site $site) use ($languageCode, $refreshedPages): void {
            $languages = $this->languages($site, $languageCode);
            $creator = app()->make(DemoCreator::class, [
                'url' => config('app.url'),
            ]);
            $rootPages = [];
            $childPages = collect();

            $this->deactivateDuplicateActiveUrlsForSite($site);

            foreach (self::RootPageNames as $pageName) {
                $page = $this->findOrCreatePage($creator, $site, $languages, $pageName);
                $refreshedPage = $creator->refreshDemoPage($page, $languages, refreshUrls: false);
                $refreshedPages->push($refreshedPage);
                $rootPages[$pageName] = $refreshedPage;
            }

            foreach (self::ChildPageParents as $pageName => $parentName) {
                $parent = $rootPages[$parentName] ?? $this->findOrCreatePage($creator, $site, $languages, $parentName);
                $page = $this->findOrCreatePage($creator, $site, $languages, $pageName, $parent);
                $refreshedPage = $creator->refreshDemoPage($page, $languages, refreshUrls: false);
                $refreshedPages->push($refreshedPage);
                $childPages->push($refreshedPage);
            }

            $refreshedSitePages = collect($rootPages)->values()->merge($childPages);

            $this->deactivateDuplicateActiveUrls($refreshedSitePages);

            collect($rootPages)->each(function (Page $page): void {
                $this->deactivateConflictingUrlsForPage($page);

                SetupPageUrlsAction::run($page);
            });

            $childPages->each(function (Page $page): void {
                $this->deactivateConflictingUrlsForPage($page);

                SetupPageUrlsAction::run($page);
            });

            $this->deactivateDuplicateActiveUrls($refreshedSitePages);
        });

        return $refreshedPages;
    }

    /**
     * @return EloquentCollection<int, Site>
     */
    private function sites(?string $siteName): EloquentCollection
    {
        $query = Site::query()->with(['languages']);

        if (is_string($siteName) && $siteName !== '') {
            $query->where('name', $siteName);
        } else {
            $query->where('default', true);
        }

        $sites = $query->get();

        throw_if($sites->isEmpty(), InvalidArgumentException::class, 'No matching site was found for the Stitch demo refresh.');

        if (is_string($siteName) && $siteName !== '' && $sites->count() > 1) {
            throw new InvalidArgumentException(sprintf('Multiple sites were found with the name [%s]. Use a unique demo site name.', $siteName));
        }

        return $sites;
    }

    /**
     * @return EloquentCollection<int, Language>
     */
    private function languages(Site $site, ?string $languageCode): EloquentCollection
    {
        $languages = $site->languages;

        if (is_string($languageCode) && $languageCode !== '') {
            $languages = $languages->where('code', $languageCode)->values();
        } else {
            $languages = $languages->where('code', 'en')->values();

            if ($languages->isEmpty()) {
                $languages = $site->languages->where('default', true)->values();
            }

            if ($languages->isEmpty()) {
                $languages = $site->languages->take(1)->values();
            }
        }

        if ($languages->isEmpty()) {
            throw new InvalidArgumentException(sprintf('No matching language was found for site [%s].', $site->name));
        }

        return $languages;
    }

    /**
     * @param  EloquentCollection<int, Language>  $languages
     */
    private function findOrCreatePage(DemoCreator $creator, Site $site, EloquentCollection $languages, string $pageName, ?Page $parent = null): Page
    {
        $expectedParentId = $parent?->getKey();

        $pages = Page::query()
            ->where('site_id', $site->getKey())
            ->where('name', $pageName)
            ->oldest('id')
            ->get();

        $page = $pages->first(fn (Page $page): bool => $page->parent_id === $expectedParentId)
            ?? $pages->first();

        if (! $page instanceof Page) {
            $page = $creator->createPage(
                [
                    'name' => ['en' => $pageName],
                    'media_count' => 0,
                ],
                $site,
                $languages,
                $parent,
                createMedia: false,
            );
        }

        throw_unless($page instanceof Page, RuntimeException::class, 'Demo page creator must return a page model.');

        if ($page->parent_id !== $expectedParentId) {
            $page->forceFill(['parent_id' => $expectedParentId])->save();
        }

        return $page;
    }

    private function deactivateConflictingUrlsForPage(Page $page): void
    {
        $page->loadMissing(['translations.language']);

        $page->translations->each(function (Model $translation) use ($page): void {
            $language = $translation->language;

            if ($language === null) {
                return;
            }

            $url = $page->getParentUrl($language);
            $slug = $translation->slug;

            if (! str_starts_with($url, '/')) {
                $url = '/' . $url;
            }

            if ($slug !== '/') {
                if (! str_ends_with($url, '/')) {
                    $url .= '/';
                }

                $url .= $slug;
            }

            PageUrl::query()
                ->where('site_id', $page->site_id)
                ->where('language_id', $translation->language_id)
                ->where('url', $url)
                ->where('status', true)
                ->where(function (Builder $query) use ($page): void {
                    $query
                        ->where('pageable_type', '!=', $page->getMorphClass())
                        ->orWhere('pageable_id', '!=', $page->getKey());
                })
                ->update(['status' => false]);
        });
    }

    private function deactivateDuplicateActiveUrlsForSite(Site $site): void
    {
        PageUrl::query()
            ->where('site_id', $site->getKey())
            ->where('status', true)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (PageUrl $pageUrl): string => $pageUrl->language_id . '|' . $pageUrl->url)
            ->each(function (Collection $pageUrls): void {
                PageUrl::query()
                    ->whereKey($pageUrls->skip(1)->pluck('id')->all())
                    ->update(['status' => false]);
            });
    }

    /**
     * @param  Collection<int, Page>  $pages
     */
    private function deactivateDuplicateActiveUrls(Collection $pages): void
    {
        $pages->each(function (Page $page): void {
            $page->pageUrls()
                ->where('status', true)
                ->get()
                ->each(function (Model $pageUrl): void {
                    PageUrl::query()
                        ->where('site_id', $pageUrl->site_id)
                        ->where('language_id', $pageUrl->language_id)
                        ->where('url', $pageUrl->url)
                        ->where('status', true)
                        ->whereKeyNot($pageUrl->getKey())
                        ->update(['status' => false]);
                });
        });
    }
}
