<?php

declare(strict_types=1);

use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\Core\Models\Widget;
use Capell\DemoKit\Actions\Diagnostics\AssertDefaultDemoInstallHealthAction;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;

it('passes the showcase order asset and placeholder demo checks for curated homepage data', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $layout = createDemoHealthLayout($site, showcaseWidgetKeys());

    Page::factory()
        ->home()
        ->site($site)
        ->layout($layout)
        ->withTranslations($language, ['title' => 'Home'])
        ->create();

    foreach (showcaseWidgetKeys() as $key) {
        createDemoHealthWidget($key, showcaseWidgetTitle($key));
    }

    createWidgetAssets('ap-card-grid', 3);
    createWidgetAssets('ap-feature-list', 4);
    createWidgetAssets('ap-image-gallery', 6);

    $checks = AssertDefaultDemoInstallHealthAction::run()->checks->keyBy('label');

    expect($checks['Default demo showcase widget order']->passed)->toBeTrue()
        ->and($checks['Default demo AP widget assets']->passed)->toBeTrue()
        ->and($checks['Default demo placeholder labels']->passed)->toBeTrue();
});

it('fails when the homepage keeps generic AP labels or an incomplete showcase order', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $layout = createDemoHealthLayout($site, ['ap-card-grid', 'ap-hero-banner']);

    Page::factory()
        ->home()
        ->site($site)
        ->layout($layout)
        ->withTranslations($language, ['title' => 'Home'])
        ->create();

    createDemoHealthWidget('ap-card-grid', 'AP Card Grid');

    $checks = AssertDefaultDemoInstallHealthAction::run()->checks->keyBy('label');

    expect($checks['Default demo showcase widget order']->passed)->toBeFalse()
        ->and($checks['Default demo placeholder labels']->passed)->toBeFalse()
        ->and($checks['Default demo AP widget assets']->passed)->toBeFalse();
});

/**
 * @return list<string>
 */
function showcaseWidgetKeys(): array
{
    return [
        'ap-hero-banner',
        'modern-stats',
        'ap-card-grid',
        'modern-process-steps',
        'ap-feature-list',
        'modern-alternating-content',
        'ap-image-gallery',
        'modern-testimonials',
        'modern-faq',
        'ap-cta-section',
    ];
}

/**
 * @param  list<string>  $widgetKeys
 */
function createDemoHealthLayout(Site $site, array $widgetKeys): Layout
{
    return Layout::factory()
        ->site($site)
        ->create([
            'key' => 'home',
            'widgets' => $widgetKeys,
            'containers' => [
                'ap-widgets' => [
                    'meta' => ['colspan' => 12],
                    'widgets' => array_map(
                        fn (string $widgetKey): array => ['widget_key' => $widgetKey],
                        $widgetKeys,
                    ),
                ],
            ],
        ]);
}

function createDemoHealthWidget(string $key, string $title): Widget
{
    $type = Blueprint::factory()->create([
        'type' => LayoutTypeEnum::Element->value,
    ]);

    $widget = Widget::factory()
        ->for($type, 'type')
        ->create([
            'key' => $key,
            'name' => $title,
        ]);

    Translation::factory()
        ->translatable($widget)
        ->create([
            'language_id' => Language::query()->firstOrFail()->id,
            'title' => $title,
            'content' => sprintf('<p>%s content</p>', $title),
        ]);

    return $widget;
}

function showcaseWidgetTitle(string $key): string
{
    return [
        'ap-hero-banner' => 'Capell CMS',
        'modern-stats' => 'Proof points for a healthier release',
        'ap-card-grid' => 'A complete CMS foundation',
        'modern-process-steps' => 'The publishing path Capell demonstrates',
        'ap-feature-list' => 'Everything visible is backed by editable records',
        'modern-alternating-content' => 'From model to public page',
        'ap-image-gallery' => 'Media that stays editable',
        'modern-testimonials' => 'What a release-ready Capell site should prove',
        'modern-faq' => 'Questions this demo answers',
        'ap-cta-section' => 'A demo site that proves the CMS stack is wired',
    ][$key];
}

function createWidgetAssets(string $widgetKey, int $count): void
{
    $widget = Widget::query()->where('key', $widgetKey)->firstOrFail();

    for ($index = 0; $index < $count; $index++) {
        resolve(ConnectionResolverInterface::class)->table('layout_module_assets')->insert([
            'layout_module_id' => $widget->id,
            'asset_type' => Page::query()->make()->getMorphClass(),
            'asset_id' => (string) Str::uuid(),
            'order' => $index + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
