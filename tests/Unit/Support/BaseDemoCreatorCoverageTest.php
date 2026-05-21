<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Models\Block;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function (): void {
    foreach (CapellLayoutBuilderManager::getMigrations() as $migration) {
        $instance = include dirname(__DIR__, 4) . '/layout-builder/database/migrations/' . $migration . '.php';

        $instance->up();
    }

    LayoutBuilderInstallPackageAction::run();
    resolve(BlueprintCreator::class)->createPageTypes();
    resolve(TypeCreator::class)->createBlockTypes();
});

it('creates demo page layouts for named, footer, contact, and unknown pages', function (): void {
    $creator = new class extends DemoCreator
    {
        public function layoutFor(string $name): ?Layout
        {
            return $this->layoutForDemoPage($name);
        }
    };

    $about = $creator->layoutFor('About Us');
    $faq = $creator->layoutFor('FAQ');
    $footer = $creator->layoutFor('Integrations');
    $contact = $creator->layoutFor('Contact');
    $unknown = $creator->layoutFor('Unknown Page');

    expect($about?->key)->toBe('capell-demo-about')
        ->and($about?->blocks)->toBe(['demo-page-hero', 'breadcrumbs', 'demo-page-content'])
        ->and($faq?->key)->toBe('capell-demo-faq-no-hero')
        ->and($faq?->blocks)->toBe(['breadcrumbs', 'demo-page-content'])
        ->and($footer?->key)->toBe('footer-standard')
        ->and($footer?->blocks)->toBe(['breadcrumbs', 'demo-page-content'])
        ->and($contact?->key)->toBe('contact-standalone')
        ->and($contact?->blocks)->toBe(['breadcrumbs', 'demo-page-content', 'contact-form'])
        ->and($unknown)->toBeNull()
        ->and(Block::query()->where('key', 'demo-page-content')->first()?->meta['page_content'])->toBe(['content']);
});

it('normalizes demo page metadata content summaries and hero snippets', function (): void {
    $creator = new class extends DemoCreator
    {
        /**
         * @return array<string, mixed>
         */
        public function metaFor(string $name): array
        {
            return $this->demoPageMeta($name);
        }

        public function contentFor(string $name, string $languageCode): ?string
        {
            return $this->demoPageContent($name, $languageCode);
        }

        public function heroFor(string $name, string $content): ?string
        {
            return $this->demoPageHeroContent($name, $content);
        }

        public function summaryFor(string $name): ?string
        {
            return $this->demoPageSummary($name);
        }

        public function canonicalName(string $name): string
        {
            return $this->canonicalDemoPageName($name);
        }
    };

    expect($creator->metaFor('Homepage 2'))
        ->toMatchArray(['show_hero' => true, 'hero_style' => 'immersive', 'header_over_hero' => true])
        ->and($creator->metaFor('Implementation'))->toMatchArray(['show_hero' => false, 'hero_style' => 'default'])
        ->and($creator->contentFor('Services', 'en'))->toContain('Implementation services cover content modelling')
        ->and($creator->contentFor('Services', 'fr'))->toBeNull()
        ->and($creator->heroFor('FAQ', '<p>Question. Answer.</p>'))->toBeNull()
        ->and($creator->heroFor('Services', '<p>First sentence. Second sentence.</p>'))->toBe('<p>First sentence.</p>')
        ->and($creator->summaryFor('Compliance'))->toContain('Regional compliance')
        ->and($creator->summaryFor('Services'))->toBeNull()
        ->and($creator->canonicalName('faq'))->toBe('FAQ')
        ->and($creator->canonicalName('home, buildings and architecture'))->toBe('Platform Architecture');
});

it('falls back to page names for navigation labels when navigation is not installed', function (): void {
    $creator = new class extends DemoCreator
    {
        /**
         * @return array<string, mixed>
         */
        public function itemsFor(Collection $pages, Language $language): array
        {
            return $this->navigationPageItems($pages, $language);
        }
    };

    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $page = Page::factory()->site($site)->withTranslations($language, ['title' => 'Translated Label'])->create(['name' => 'Fallback Label']);
    $child = Page::factory()->site($site)->parent($page)->withTranslations($language, ['title' => 'Child Label'])->create(['name' => 'Child']);

    $page->setRelation('translation', null);
    $page->setRelation('children', new Collection([$child]));

    $items = $creator->itemsFor(new Collection([$page]), $language);
    $item = array_values($items)[0];

    expect($item['label'])->toBe('Fallback Label')
        ->and($item['children'])->toHaveCount(1);
});
