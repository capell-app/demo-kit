<?php

declare(strict_types=1);

use Capell\Core\Enums\ContainerWidthEnum;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\DemoKit\Actions\InstallKitchenSinkDemoPageAction;
use Capell\Frontend\Support\CapellFrontendContext;
use Capell\Frontend\Support\State\FrontendState;
use Capell\LayoutBuilder\Actions\Fragments\RenderPublicFragmentAction;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;

it('installs the kitchen sink demo page idempotently', function (): void {
    $firstPage = InstallKitchenSinkDemoPageAction::run();
    $secondPage = InstallKitchenSinkDemoPageAction::run();

    $layout = Layout::query()->firstWhere('key', 'kitchen-sink-demo');

    expect($secondPage->getKey())->toBe($firstPage->getKey())
        ->and(Page::query()->where('name', 'Kitchen Sink Demo Page')->count())->toBe(1)
        ->and($layout)->not->toBeNull()
        ->and($layout->containers['main']['widgets'])->toHaveCount(7)
        ->and(WidgetAsset::query()->where('pageable_id', $secondPage->getKey())->count())->toBe(7);
});

it('stores lazy presentation metadata only on below fold kitchen sink layout instances', function (): void {
    InstallKitchenSinkDemoPageAction::run();

    $layout = Layout::query()->firstWhere('key', 'kitchen-sink-demo');
    $layoutContainers = $layout?->containers;
    expect(is_array($layoutContainers))->toBeTrue();

    $layoutWidgets = is_array($layoutContainers['main']['widgets'] ?? null)
        ? $layoutContainers['main']['widgets']
        : [];

    $blocks = collect($layoutWidgets)->keyBy('widget_key');

    expect($blocks->get('kitchen-sink-structured-text'))->not->toHaveKey('meta.presentation');

    foreach ([
        'kitchen-sink-rich-text',
        'kitchen-sink-data-display',
        'kitchen-sink-interactions',
        'kitchen-sink-embeds',
        'kitchen-sink-forms',
        'kitchen-sink-utility-states',
    ] as $blockKey) {
        expect($blocks->get($blockKey)['meta']['presentation'] ?? null)->toBe([
            'delivery_mode' => 'lazy_fragment',
            'loading_strategy' => 'visible',
        ]);
    }
});

it('stores one page h1 and all forty reference section headings', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run();
    $translation = $page->translations()->first();

    expect(substr_count((string) $translation?->content, '<h1>'))->toBe(1);

    $layoutContainers = $page->layout?->containers;
    expect(is_array($layoutContainers))->toBeTrue();

    $layoutWidgets = is_array($layoutContainers['main']) && is_array($layoutContainers['main']['widgets'] ?? null)
        ? $layoutContainers['main']['widgets']
        : [];

    $headings = collect($layoutWidgets)
        ->pluck('widget_key')
        ->map(fn (string $key) => Widget::query()->firstWhere('key', $key)?->meta['sections'] ?? [])
        ->flatten(1)
        ->pluck('heading')
        ->values()
        ->all();

    expect($headings)->toBe(InstallKitchenSinkDemoPageAction::sectionHeadings());
});

it('renders the structured text block eagerly and lazy placeholders for below fold sections', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run();
    $html = kitchenSinkLayoutHtml($page);

    expect(substr_count($html, '<h1>'))->toBe(1)
        ->and($html)->toContain('Kitchen Sink Demo Page')
        ->and($html)->toContain('Hero')
        ->and($html)->toContain('Breadcrumbs')
        ->and($html)->toContain('Table of contents')
        ->and(preg_match_all('/\sdata-capell-fragment(\s|>)/', $html))->toBe(6)
        ->and($html)->not->toContain('Paragraph styles')
        ->and($html)->not->toContain('Full form')
        ->and($html)->not->toContain('kitchen-sink-rich-text')
        ->and($html)->not->toContain('capell-app')
        ->and($html)->not->toContain('data-field-path')
        ->and($html)->not->toContain('data-capell-authoring')
        ->and($html)->not->toContain('signed');
});

it('fetches each lazy kitchen sink fragment through the public fragment route', function (): void {
    $page = InstallKitchenSinkDemoPageAction::run();
    $html = kitchenSinkLayoutHtml($page);
    preg_match_all('/data-capell-fragment-url="([^"]+)"/', $html, $matches);

    expect($matches[1])->toHaveCount(6);

    $fragmentHtml = '';

    foreach ($matches[1] as $fragmentUrl) {
        $path = (string) parse_url(html_entity_decode($fragmentUrl), PHP_URL_PATH);

        $this->get($path)->assertOk();

        $reference = rawurldecode((string) str($path)->after('/_capell/fragments/'));
        $fragmentHtml .= RenderPublicFragmentAction::run($reference);
    }

    expect($html . $fragmentHtml)->toContain('Hero')
        ->and($html . $fragmentHtml)->toContain('Footer')
        ->and(kitchenSinkHeadingsFromHtml($html . $fragmentHtml))->toBe(InstallKitchenSinkDemoPageAction::sectionHeadings())
        ->and($fragmentHtml)->toContain('FAQ accordion')
        ->and($fragmentHtml)->toContain('role="tablist"')
        ->and($fragmentHtml)->toContain('aria-expanded="false"')
        ->and($fragmentHtml)->toContain('<table>')
        ->and($fragmentHtml)->toContain('<form')
        ->and($fragmentHtml)->toContain('aria-labelledby');
});

function kitchenSinkLayoutHtml(Page $page): string
{
    $page->loadMissing(['layout', 'site', 'translations.language']);
    $language = $page->translations->first()?->language;

    app()->instance(
        CapellFrontendContext::class,
        new CapellFrontendContext(
            (new FrontendState)
                ->withSite($page->site)
                ->withLanguage($language)
                ->withPage($page)
                ->withLayout($page->layout),
        ),
    );

    $container = $page->layout->containers['main'];
    $translation = $page->translations->first();
    $html = (string) $translation?->content;

    foreach ($container['widgets'] as $blockIndex => $blockData) {
        $block = Widget::query()->firstWhere('key', $blockData['widget_key']);

        if (! $block instanceof Widget) {
            continue;
        }

        $html .= view('capell-layout-builder::components.layout.block', [
            'component' => $block->getComponent(),
            'containerColspan' => 12,
            'container' => $container,
            'containerKey' => 'main',
            'containerIndex' => 0,
            'containerWidth' => ContainerWidthEnum::Full,
            'loop' => (object) ['index' => $blockIndex],
            'layout' => $page->layout,
            'type' => $block->getMetaComponentType(),
            'block' => $block,
            'blockIndex' => $blockIndex,
            'blockData' => $blockData,
            'pageSlot' => null,
        ])->render();
    }

    return $html;
}

/**
 * @return array<int, string>
 */
function kitchenSinkHeadingsFromHtml(string $html): array
{
    preg_match_all('/<h2[^>]*>(.*?)<\/h2>/s', $html, $matches);

    return collect($matches[1])->map(fn (string $heading): string => trim(strip_tags($heading)))->values()->all();
}
