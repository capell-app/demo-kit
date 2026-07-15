<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Site;
use Capell\DemoKit\Actions\InstallKitchenSinkDemoPageAction;
use Capell\DemoKit\Support\KitchenSinkPublicLayoutWidgetPayloadContributor;
use Capell\LayoutBuilder\Actions\BuildPublicLayoutGraphAction;
use Capell\LayoutBuilder\Data\PublicLayoutContainerData;
use Capell\LayoutBuilder\Data\PublicLayoutGraphData;
use Capell\LayoutBuilder\Models\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

/**
 * Stress the public render of the canonical Kitchen Sink demo page — the Demo Kit reference
 * fixture that exercises every widget family (structured text, rich text, data display,
 * interactions, embeds, forms, utility states) plus lazy fragments and widget assets.
 *
 * Building its public layout graph must stay within a bounded query budget (widget,
 * translation and asset loading is batched, not N+1) and must never leak authoring
 * metadata into the public payload.
 */
it('builds the kitchen sink demo layout graph within a bounded query budget without authoring leakage', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run()->loadMissing(['layout', 'site', 'translations.language']);

    $layout = $page->layout;
    $site = $page->site;
    $language = $page->translations->first()?->language;

    expect($layout)->toBeInstanceOf(Layout::class);
    expect($site instanceof Site)->toBeTrue();
    expect($language instanceof Language)->toBeTrue();

    assert($layout instanceof Layout);
    assert($site instanceof Site);
    assert($language instanceof Language);

    // Avoid a lazy site reload skewing the measured query count.
    $page->setRelation('site', $site);

    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    $graph = BuildPublicLayoutGraphAction::run($layout, $page, $language);

    $renderedWidgetCount = array_sum(array_map(
        static fn (PublicLayoutContainerData $container): int => count($container->widgets),
        $graph->containers,
    ));
    $serialized = json_encode($graph, JSON_THROW_ON_ERROR);

    expect($graph)->toBeInstanceOf(PublicLayoutGraphData::class)
        ->and($renderedWidgetCount)->toBe(count(InstallKitchenSinkDemoPageAction::layoutWidgetKeys()))
        ->and($queryCount)->toBeLessThan(32)
        ->and($serialized)->not->toContain('admin_schema')
        ->and($serialized)->not->toContain('signed_url')
        ->and($serialized)->not->toContain('widget_settings')
        ->and($serialized)->not->toContain('data-capell-authoring')
        ->and($serialized)->not->toContain('data-field-path');
})->group('demo-kit', 'stress');

it('keeps kitchen sink graph queries flat when built repeatedly', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run()->loadMissing(['layout', 'site', 'translations.language']);

    $layout = $page->layout;
    $language = $page->translations->first()?->language;

    assert($layout instanceof Layout);
    assert($language instanceof Language);

    $page->setRelation('site', $page->site);

    $countQueries = function () use ($layout, $page, $language): int {
        $queryCount = 0;
        $listener = function () use (&$queryCount): void {
            $queryCount++;
        };

        DB::listen($listener);
        BuildPublicLayoutGraphAction::run($layout, $page, $language);

        return $queryCount;
    };

    $firstBuild = $countQueries();
    $secondBuild = $countQueries();

    // A second build of the same kitchen sink graph must not issue extra queries — resolver
    // and theme/asset caches are reused rather than re-fetched per build.
    expect($secondBuild)->toBeLessThanOrEqual($firstBuild);
})->group('demo-kit', 'stress');

it('renders kitchen sink contributor HTML from the preloaded widget payload only', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run()->loadMissing(['layout', 'site', 'translations.language']);
    $language = $page->translations->first()?->language;
    $widget = Widget::query()
        ->with('translation')
        ->firstWhere('key', 'kitchen-sink-structured-text');

    if (! $language instanceof Language || ! $widget instanceof Widget) {
        throw new LogicException('Expected a translated kitchen sink widget.');
    }

    $translation = $widget->translation;

    if (! $translation instanceof Model) {
        throw new LogicException('Expected a loaded widget translation.');
    }

    $translation->forceFill([
        'content' => '<p>Safe content</p><script>alert(1)</script><span data-field-path="content">hidden authoring</span>',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $contributor = resolve(KitchenSinkPublicLayoutWidgetPayloadContributor::class);
    $data = $contributor->data($widget, $page, $language, 'main', 1);
    $html = $contributor->html($widget, $page, $language, 'main', 1);
    $queryCount = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($html)
        ->toContain('<p>Safe content</p>')
        ->not->toContain('<script')
        ->not->toContain('data-field-path')
        ->and(data_get($data, 'sections.0.html'))->toContain('<p>Safe content</p>')
        ->and(json_encode($data, JSON_THROW_ON_ERROR))->not->toContain('data-field-path')
        ->and($queryCount)->toBe(0);
})->group('demo-kit', 'stress');

it('renders the kitchen sink public route from its query-free graph projection', function (): void {
    config()->set('capell-demo-kit.kitchen_sink.target_widget_count', 32);
    config()->set('capell-demo-kit.kitchen_sink.eager_widget_limit', 12);
    config()->set('capell-demo-kit.kitchen_sink.context_page_count', 8);
    config()->set('capell-demo-kit.kitchen_sink.context_asset_limit', 6);
    config()->set('capell-frontend.public_view_query_guard.enabled', true);
    config()->set('capell-frontend.public_view_query_guard.mode', 'exception');

    $page = InstallKitchenSinkDemoPageAction::run()->loadMissing('pageUrl.siteDomain');
    $pageUrl = $page->pageUrl;

    if ($pageUrl === null) {
        throw new LogicException('Expected the kitchen sink page to have a public URL.');
    }

    $response = get($pageUrl->full_url);

    $response->assertOk();

    expect($response->getContent())
        ->toContain('Kitchen Sink Demo Page')
        ->toContain('Table of contents')
        ->toContain('data-deferred-fragment')
        ->not->toContain('data-capell-authoring')
        ->not->toContain('data-field-path')
        ->not->toContain('signed_url')
        ->not->toContain('capell-layout-builder');
})->group('demo-kit', 'stress');
