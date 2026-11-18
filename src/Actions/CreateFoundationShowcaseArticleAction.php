<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Enums\PageTypeEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\PageCreator;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Seeds the editorial article that the Foundation showcase demo site opens with,
 * so editor and phone-width captures can be reproduced from a local install.
 *
 * @method static Page|null run(Site $site)
 */
final class CreateFoundationShowcaseArticleAction
{
    use AsFake;
    use AsObject;

    public const string Fixture = 'foundation-showcase-article';

    public const string Slug = 'the-estuary-at-first-light';

    public function handle(Site $site): ?Page
    {
        $existing = Page::query()
            ->where('site_id', $site->getKey())
            ->where('meta->demo_fixture', self::Fixture)
            ->first();

        if ($existing instanceof Page) {
            return $existing;
        }

        $layout = Layout::query()->firstWhere('key', LayoutEnum::Default);
        $languages = $site->languages;

        if (! $layout instanceof Layout || $languages->isEmpty()) {
            return null;
        }

        $translations = [];

        foreach ($languages as $language) {
            $translations[(string) $language->code] = $this->translation($language);
        }

        /** @var Page $page */
        $page = resolve(PageCreator::class)->createPage([
            'name' => 'The estuary at first light',
            'layout_id' => $layout->getKey(),
            'type_key' => PageTypeEnum::Default,
            'visible_from' => now()->subDay()->format('Y-m-d'),
            'meta' => ['demo_fixture' => self::Fixture],
            'translations' => $translations,
        ], $site, $languages);

        SetupPageUrlsAction::run($page);

        return $page->refresh();
    }

    /** @return array<string, mixed> */
    private function translation(Language $language): array
    {
        $copy = $language->code === 'fr' ? $this->french() : $this->english();

        return [
            'title' => $copy['title'],
            'summary' => $copy['summary'],
            'content' => '<p>' . e($copy['summary']) . '</p>'
                . '<h2>' . e($copy['heading']) . '</h2>'
                . '<p>' . e($copy['body']) . '</p>',
            'meta' => [
                'slug' => self::Slug,
                'label' => $copy['title'],
                'demo_fixture' => self::Fixture,
            ],
        ];
    }

    /** @return array{title: string, summary: string, heading: string, body: string} */
    private function english(): array
    {
        return [
            'title' => 'The estuary at first light',
            'summary' => 'The tide has just turned. Across the estuary, wading birds move between the shallow pools, following the water as it draws back towards the sea.',
            'heading' => 'Look slowly, listen carefully',
            'body' => 'A good field walk starts with a pause. Find a sheltered place, let your eyes settle and listen before you reach for a camera. What first looks empty soon becomes a network of small journeys.',
        ];
    }

    /** @return array{title: string, summary: string, heading: string, body: string} */
    private function french(): array
    {
        return [
            'title' => 'L’estuaire au petit jour',
            'summary' => 'La marée vient de tourner. Dans l’estuaire, les échassiers passent d’une flaque à l’autre en suivant l’eau qui se retire vers la mer.',
            'heading' => 'Regarder lentement, écouter attentivement',
            'body' => 'Une bonne sortie sur le terrain commence par une pause. Trouvez un endroit abrité, laissez vos yeux s’habituer et écoutez avant de sortir l’appareil photo. Ce qui semblait vide devient vite un réseau de petits trajets.',
        ];
    }
}
