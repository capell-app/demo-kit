<?php

declare(strict_types=1);

use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\DemoKit\Actions\Diagnostics\AssertDefaultDemoInstallHealthAction;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

beforeEach(function (): void {
    foreach (CapellLayoutBuilderManager::getMigrations() as $migration) {
        $instance = include dirname(__DIR__, 5) . '/layout-builder/database/migrations/' . $migration . '.php';

        $instance->up();
    }

    LayoutBuilderInstallPackageAction::run();
});

/**
 * @param  Collection<string, DoctorCheckResultData>  $checks
 */
function demoHealthCheck(Collection $checks, string $label): DoctorCheckResultData
{
    $check = $checks->get($label);

    if (! $check instanceof DoctorCheckResultData) {
        throw new RuntimeException(sprintf('Expected demo health check [%s] to exist.', $label));
    }

    return $check;
}

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

    $checks = AssertDefaultDemoInstallHealthAction::run()->checks->keyBy('label');

    expect(demoHealthCheck($checks, 'Default demo showcase widget order')->passed)->toBeTrue()
        ->and(demoHealthCheck($checks, 'Default demo AP widget assets')->passed)->toBeTrue()
        ->and(demoHealthCheck($checks, 'Default demo placeholder labels')->passed)->toBeTrue();
});

it('allows applications to configure the homepage health profile', function (): void {
    config()->set('capell-demo-kit.health.minimum_media_count', 0);
    config()->set('capell-demo-kit.health.homepage_opening_widget_keys', ['page-content']);
    config()->set('capell-demo-kit.health.showcase_widget_order', [
        'page-content',
        'capell-marketing-hero',
        'capell-marketing-showcase',
        'capell-marketing-cloud',
        'capell-marketing-boundaries',
        'capell-marketing-features',
        'capell-marketing-carousel',
        'capell-marketing-mcp',
    ]);

    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $layout = createDemoHealthLayout($site, [
        'page-content',
        'capell-marketing-hero',
        'capell-marketing-showcase',
        'capell-marketing-cloud',
        'capell-marketing-boundaries',
        'capell-marketing-features',
        'capell-marketing-carousel',
        'capell-marketing-mcp',
    ]);

    Page::factory()
        ->home()
        ->site($site)
        ->layout($layout)
        ->withTranslations($language, ['title' => 'Home'])
        ->create();

    $checks = AssertDefaultDemoInstallHealthAction::run()->checks->keyBy('label');

    expect(demoHealthCheck($checks, 'Homepage starts with a hero widget')->passed)->toBeTrue()
        ->and(demoHealthCheck($checks, 'Default demo showcase widget order')->passed)->toBeTrue()
        ->and(demoHealthCheck($checks, 'Default demo media count')->passed)->toBeTrue();
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

    expect(demoHealthCheck($checks, 'Default demo showcase widget order')->passed)->toBeFalse()
        ->and(demoHealthCheck($checks, 'Default demo placeholder labels')->passed)->toBeFalse();
});

it('reports actionable demo health failures before homepage content and media are installed', function (): void {
    $checks = AssertDefaultDemoInstallHealthAction::run()->checks->keyBy('label');

    expect(demoHealthCheck($checks, 'Layout Builder demo dependency')->passed)->toBeTrue()
        ->and(demoHealthCheck($checks, 'Default demo homepage exists')->passed)->toBeFalse()
        ->and(demoHealthCheck($checks, 'Homepage layout has widgets')->passed)->toBeFalse()
        ->and(demoHealthCheck($checks, 'Homepage starts with a hero widget')->passed)->toBeFalse()
        ->and(demoHealthCheck($checks, 'Default demo showcase widget order')->passed)->toBeFalse()
        ->and(demoHealthCheck($checks, 'Default demo widget count')->passed)->toBeFalse()
        ->and(demoHealthCheck($checks, 'Default demo AP widget assets')->passed)->toBeTrue()
        ->and(demoHealthCheck($checks, 'Default demo media count')->passed)->toBeFalse();
});

/**
 * @return list<string>
 */
function showcaseWidgetKeys(): array
{
    return [
        'capell-home-hero-command-center',
        'capell-home-proof-strip',
        'capell-home-demo-showcase',
        'capell-home-demo-widgets-carousel',
        'capell-extension-marketplace-showcase',
        'capell-home-technical-pipeline',
        'capell-home-route-split',
        'capell-home-final-cta',
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
        'type' => LayoutTypeEnum::Widget->value,
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
        'capell-home-hero-command-center' => 'Capell CMS',
        'capell-home-proof-strip' => 'Proof points for a healthier release',
        'capell-home-demo-showcase' => 'A complete CMS foundation',
        'capell-home-demo-widgets-carousel' => 'Interactive demo widgets',
        'capell-extension-marketplace-showcase' => 'Extension marketplace showcase',
        'capell-home-technical-pipeline' => 'Everything visible is backed by editable records',
        'capell-home-route-split' => 'From model to public page',
        'capell-home-final-cta' => 'A demo site that proves the CMS stack is wired',
    ][$key];
}

function createWidgetAssets(string $widgetKey, int $count): void
{
    $widget = Widget::query()->where('key', $widgetKey)->firstOrFail();

    for ($index = 0; $index < $count; $index++) {
        resolve(ConnectionResolverInterface::class)->table('widget_assets')->insert([
            'widget_id' => $widget->id,
            'asset_type' => Page::query()->make()->getMorphClass(),
            'asset_id' => (string) Str::uuid(),
            'order' => $index + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
