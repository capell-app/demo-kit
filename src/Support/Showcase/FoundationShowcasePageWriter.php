<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Showcase;

use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Enums\PageTypeEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\PageCreator;
use Closure;

/**
 * Writes one idempotent Foundation showcase page, keyed by its `demo_fixture` marker,
 * so each editor and phone-width capture pair can be reproduced from a local install.
 */
final class FoundationShowcasePageWriter
{
    public function find(Site $site, string $fixture): ?Page
    {
        $page = Page::query()
            ->where('site_id', $site->getKey())
            ->where('meta->demo_fixture', $fixture)
            ->first();

        return $page instanceof Page ? $page : null;
    }

    /**
     * @param  Closure(Language): array{title: string, summary: string, heading: string, body: string}  $copy
     */
    public function write(
        Site $site,
        string $fixture,
        string $slug,
        string $name,
        Closure $copy,
        ?Layout $layout = null,
        ?Page $parent = null,
    ): ?Page {
        $existing = $this->find($site, $fixture);

        if ($existing instanceof Page) {
            return $existing;
        }

        $layout ??= Layout::query()->firstWhere('key', LayoutEnum::Default);
        $languages = $site->languages;

        if (! $layout instanceof Layout || $languages->isEmpty()) {
            return null;
        }

        $translations = [];

        foreach ($languages as $language) {
            $translations[(string) $language->code] = $this->translation($copy($language), $slug, $fixture);
        }

        /** @var Page $page */
        $page = resolve(PageCreator::class)->createPage([
            'name' => $name,
            'layout_id' => $layout->getKey(),
            'type_key' => PageTypeEnum::Default,
            'parent_id' => $parent?->getKey(),
            'visible_from' => now()->subDay()->format('Y-m-d'),
            'meta' => ['demo_fixture' => $fixture],
            'translations' => $translations,
        ], $site, $languages);

        SetupPageUrlsAction::run($page);

        return $page->refresh();
    }

    /**
     * @param  array{title: string, summary: string, heading: string, body: string}  $copy
     * @return array<string, mixed>
     */
    private function translation(array $copy, string $slug, string $fixture): array
    {
        return [
            'title' => $copy['title'],
            'summary' => $copy['summary'],
            'content' => '<p>' . e($copy['summary']) . '</p>'
                . '<h2>' . e($copy['heading']) . '</h2>'
                . '<p>' . e($copy['body']) . '</p>',
            'meta' => [
                'slug' => $slug,
                'label' => $copy['title'],
                'demo_fixture' => $fixture,
            ],
        ];
    }
}
