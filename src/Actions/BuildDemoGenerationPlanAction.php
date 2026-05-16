<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\DemoKit\Data\DemoGenerationPlanData;
use Capell\DemoKit\Data\DemoPagePlanData;
use Capell\DemoKit\Data\DemoProfileData;
use Capell\DemoKit\Data\DemoSiteGenerationPlanData;
use Capell\DemoKit\Support\DemoContentPool;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static DemoGenerationPlanData run(array $options = [])
 */
final class BuildDemoGenerationPlanAction
{
    use AsObject;

    public const MAX_SITE_COUNT = 25;

    public const MAX_PAGE_COUNT = 250;

    public function __construct(
        private readonly DemoContentPool $contentPool = new DemoContentPool,
    ) {}

    /**
     * @param  array{sites?: list<string>, site_count?: int, pages?: int, languages?: list<string>, seed?: int|null}  $options
     */
    public function handle(array $options = []): DemoGenerationPlanData
    {
        $profile = DemoProfileData::default();
        $seed = array_key_exists('seed', $options) ? $options['seed'] : $profile->seed;

        if (is_int($seed)) {
            mt_srand($seed);
        } else {
            mt_srand(random_int(1, PHP_INT_MAX));
        }

        $languageCodes = $this->resolveLanguageCodes($options['languages'] ?? []);
        $siteNames = $this->resolveSiteNames($options['sites'] ?? [], $options['site_count'] ?? null);
        $pagesPerSite = $this->resolvePageCount($options['pages'] ?? null, $profile);

        return new DemoGenerationPlanData(
            seed: $seed,
            languageCodes: $languageCodes,
            sites: array_map(
                fn (string $siteName, int $siteIndex): DemoSiteGenerationPlanData => new DemoSiteGenerationPlanData(
                    name: $siteName,
                    languageCodes: $this->siteLanguageCodes($languageCodes, $siteIndex, $profile),
                    pages: $this->buildPages($pagesPerSite, $profile),
                ),
                $siteNames,
                array_keys($siteNames),
            ),
            profile: $profile,
        );
    }

    /**
     * @param  list<string>  $requested
     * @return list<string>
     */
    private function resolveLanguageCodes(array $requested): array
    {
        $available = array_keys($this->contentPool->languages());

        if ($requested === [] || $requested === ['all']) {
            return $available;
        }

        $random = collect($requested)->first(fn (string $value): bool => str_starts_with($value, 'random:'));
        if (is_string($random)) {
            $count = max(1, min((int) Str::after($random, 'random:'), count($available)));

            return $this->takeRandom($available, $count);
        }

        $matchedLanguages = array_values(array_intersect($requested, $available));

        return $matchedLanguages !== [] ? $matchedLanguages : ['en'];
    }

    /**
     * @param  list<string>  $requested
     * @return list<string>
     */
    private function resolveSiteNames(array $requested, ?int $siteCount): array
    {
        if ($requested !== []) {
            return array_values(array_unique($requested));
        }

        $count = min($siteCount ?? DemoProfileData::default()->counts['sites'], self::MAX_SITE_COUNT);
        $names = $this->takeRandom($this->contentPool->siteNames(), $count);

        while (count($names) < $count) {
            $names[] = 'Demo Site ' . (count($names) + 1);
        }

        return $names;
    }

    private function resolvePageCount(?int $requested, DemoProfileData $profile): int
    {
        if ($requested !== null && $requested > 0) {
            return min($requested, self::MAX_PAGE_COUNT);
        }

        return mt_rand($profile->counts['pages_per_site'][0], $profile->counts['pages_per_site'][1]);
    }

    /**
     * @param  list<string>  $available
     * @return list<string>
     */
    private function siteLanguageCodes(array $available, int $siteIndex, DemoProfileData $profile): array
    {
        if ($siteIndex === 0) {
            return $available;
        }

        $count = mt_rand($profile->counts['languages_per_site'][0], min($profile->counts['languages_per_site'][1], count($available)));

        return $this->takeRandom($available, $count);
    }

    /**
     * @return list<DemoPagePlanData>
     */
    private function buildPages(int $count, DemoProfileData $profile): array
    {
        $specialPages = array_slice($this->specialDemoPages(), 0, $count);
        $remainingCount = max(0, $count - $this->countPages($specialPages));
        $availableNames = $this->availablePageNames($remainingCount);
        $pages = [];

        foreach (array_slice($availableNames, 0, $remainingCount) as $name) {
            $pages[] = new DemoPagePlanData(
                name: $this->translatedName($name),
                mediaCount: mt_rand($profile->counts['media_per_page'][0], $profile->counts['media_per_page'][1]),
            );
        }

        return [
            ...$specialPages,
            ...$this->nestPages($pages, $profile),
        ];
    }

    /**
     * @return list<string>
     */
    private function availablePageNames(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $excluded = [
            'About Us',
            'Homepage 2',
            'Contact',
            'Services',
            'Team',
            'FAQ',
            'Pricing',
            'Testimonials',
            'Projects',
            'Project Detail',
            'Blog',
            'Home, Buildings and Architecture',
            'Implementation',
            'Resources',
            'Integrations',
            'Locations',
            'Partners',
            'Roadmap',
            'Governance',
            'Training',
        ];
        $requestedCount = $count + count($excluded);
        $names = [];

        while (count($names) < $count) {
            $names = array_values(array_diff($this->pageNamesForCount($requestedCount), $excluded));
            $requestedCount += max(1, count($this->contentPool->pageNames()));
        }

        return array_slice($names, 0, $count);
    }

    /**
     * @return list<DemoPagePlanData>
     */
    private function specialDemoPages(): array
    {
        return [
            new DemoPagePlanData(
                name: $this->translatedName('Contact'),
                mediaCount: 0,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Pricing'),
                mediaCount: 0,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Resources'),
                mediaCount: 0,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('About Us'),
                mediaCount: 2,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Homepage 2'),
                mediaCount: 3,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Services'),
                mediaCount: 2,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Team'),
                mediaCount: 2,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('FAQ'),
                mediaCount: 1,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Testimonials'),
                mediaCount: 2,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Projects'),
                mediaCount: 3,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Project Detail'),
                mediaCount: 3,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Blog'),
                mediaCount: 3,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Home, Buildings and Architecture'),
                mediaCount: 2,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Integrations'),
                mediaCount: 0,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Locations'),
                mediaCount: 0,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Partners'),
                mediaCount: 0,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Roadmap'),
                mediaCount: 0,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Governance'),
                mediaCount: 0,
            ),
            new DemoPagePlanData(
                name: $this->translatedName('Training'),
                mediaCount: 0,
            ),
        ];
    }

    /**
     * @param  list<DemoPagePlanData>  $pages
     */
    private function countPages(array $pages): int
    {
        return array_reduce(
            $pages,
            fn (int $count, DemoPagePlanData $page): int => $count + 1 + $this->countPages($page->children),
            0,
        );
    }

    /**
     * @return list<string>
     */
    private function pageNamesForCount(int $count): array
    {
        $baseNames = $this->takeRandom($this->contentPool->pageNames(), count($this->contentPool->pageNames()));
        $names = [];

        while (count($names) < $count) {
            foreach ($baseNames as $baseName) {
                $names[] = count($names) < count($baseNames)
                    ? $baseName
                    : sprintf('%s %d', $baseName, intdiv(count($names), count($baseNames)) + 1);

                if (count($names) === $count) {
                    break;
                }
            }
        }

        return $names;
    }

    /**
     * @param  list<DemoPagePlanData>  $pages
     * @return list<DemoPagePlanData>
     */
    private function nestPages(array $pages, DemoProfileData $profile): array
    {
        $roots = [];
        $maxDepth = max(1, $profile->counts['page_depth'][1]);

        foreach ($pages as $page) {
            if ($roots === [] || mt_rand(1, 100) <= 45 || $maxDepth === 1) {
                $roots[] = $page;

                continue;
            }

            $parentIndex = mt_rand(0, count($roots) - 1);
            $parent = $roots[$parentIndex];
            $children = $parent->children;
            $children[] = $page;
            $roots[$parentIndex] = new DemoPagePlanData($parent->name, $parent->mediaCount, $children);
        }

        return $roots;
    }

    /**
     * @return array<string, string>
     */
    private function translatedName(string $name): array
    {
        return collect(array_keys($this->contentPool->languages()))
            ->mapWithKeys(fn (string $code): array => [$code => $name])
            ->all();
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function takeRandom(array $items, int $count): array
    {
        $items = array_values($items);

        for ($index = count($items) - 1; $index > 0; $index--) {
            $swapIndex = mt_rand(0, $index);
            [$items[$index], $items[$swapIndex]] = [$items[$swapIndex], $items[$index]];
        }

        return array_slice($items, 0, max(0, $count));
    }
}
