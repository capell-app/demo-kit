<?php

declare(strict_types=1);

namespace Capell\DemoKit\Tests\Fixtures;

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\DemoKit\Actions\MarkDemoSiteProvenanceAction;

final class GeneratedHomepage
{
    public static function create(string $name): Site
    {
        $language = Language::factory()->create(['status' => true]);
        $site = Site::factory()->withTranslations($language, siteDomainData: [
            'domain' => 'generated.example.test', 'scheme' => 'https', 'path' => '/demo', 'status' => true,
        ])->create(['name' => $name, 'language_id' => $language->getKey(), 'status' => true]);
        MarkDemoSiteProvenanceAction::run($site);
        $page = Page::factory()->site($site)->create(['visible_from' => now()->subDay()]);
        Translation::factory()->translatable($page)->language($language)->create();
        $url = PageUrl::query()->where('site_id', $site->getKey())->where('language_id', $language->getKey())
            ->where('pageable_type', $page->getMorphClass())->where('pageable_id', $page->getKey())->firstOrFail();
        $url->update(['url' => '/', 'status' => true, 'type' => null]);

        return $site;
    }
}
