<?php

declare(strict_types=1);

use Capell\DemoKit\Support\HomepageDemoContent;
use Capell\LayoutBuilder\Models\Widget;

require_once __DIR__ . '/../../Support/HomepageRender.php';

it('ships concise homepage hero proof points', function (): void {
    $content = HomepageDemoContent::forWidget('capell-home-hero-command-center');

    expect($content['heading'] ?? null)->toBe('Build Laravel CMS sites without losing control')
        ->and($content['copy'] ?? null)->toBe('Model content, compose layouts, install packages, preview releases, and ship cached public pages from one Laravel-native system.')
        ->and($content['highlights'] ?? null)->toBe([
            'Typed page trees',
            'Editor-owned layouts',
            'Package-safe widgets',
            'Static cache checks',
        ]);

    $merged = HomepageDemoContent::mergeForWidget('capell-home-hero-command-center', [
        'highlights' => ['Custom proof point'],
    ]);

    expect($merged['highlights'])->toBe(['Custom proof point']);
});

it('renders edited hero proof points as a semantic list without default copy or injected markup', function (): void {
    $highlights = ['An editor-owned proof point', '<script>untrusted()</script>'];
    $html = renderHomepageWidget(new Widget([
        'key' => 'capell-home-hero-command-center',
        'meta' => ['content' => ['highlights' => $highlights]],
    ]));

    expect(domQueryCount($html, '//ul/li[normalize-space(.)="An editor-owned proof point"]'))->toBe(1)
        ->and(domQueryCount($html, '//ul/li[normalize-space(.)="<script>untrusted()</script>"]'))->toBe(1)
        ->and(domQueryCount($html, '//li/script'))->toBe(0)
        ->and($html)->not->toContain('Typed page trees');

    $empty = renderHomepageWidget(new Widget([
        'key' => 'capell-home-hero-command-center',
        'meta' => ['content' => ['highlights' => []]],
    ]));

    expect(domQueryCount($empty, '//li'))->toBe(0);
});

it('aligns the demo hero background selectors with the hero package', function (): void {
    // Required hooks and values protect the visual contract independently of formatting.
    $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/components/widget/homepage-section.blade.php');

    expect($view)
        ->toMatch('/\.widget\-capell\-home\-hero\-command\-center\s+\.hero\-background\s*,\s*/')
        ->toMatch('/\.widget\-capell\-home\-hero\-command\-center\s+\.capell\-hero\-background\s*\{\s*/');
});

it('keeps hero proof points token driven and above decorative layers', function (): void {
    // Required hooks and values protect the visual contract independently of formatting.
    $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/components/widget/homepage-section.blade.php');

    throw_unless(is_string($view), RuntimeException::class, 'Unable to read the Demo Kit homepage section view.');

    $borderFallbackCount = preg_match_all('/border-color:\s*#[0-9a-f]{6};\s*border-color:\s*color-mix\(/i', $view);
    $backgroundFallbackCount = preg_match_all('/background:\s*#[0-9a-f]{6};\s*background:\s*color-mix\(/i', $view);
    $textFallbackCount = preg_match_all('/color:\s*#[0-9a-f]{6};\s*color:\s*color-mix\(/i', $view);

    expect($view)
        ->toMatch('/\.capell\-home\-hero\-highlight\s*\{\s*/')
        ->toMatch('/var\s*\(\s*\-\-theme\-primary\s*,\s*\#315f8f\s*\)\s*/')
        ->toMatch('/var\s*\(\s*\-\-theme\-surface\s*,\s*\#ffffff\s*\)\s*/')
        ->toMatch('/var\s*\(\s*\-\-theme\-foreground\s*,\s*\#1a1c1b\s*\)\s*/')
        ->toMatch('/\.dark\s+\.capell\-home\-hero\-highlight\s*\{\s*/')
        ->toMatch('/class="capell\-home\-hero\-grid\s+relative\s+z\-10"/')
        ->toMatch('/class="capell\-home\-hero\-copy\s+grid\s+gap\-6"/')
        ->toMatch('/class="capell\-home\-hero\-highlight\s+rounded\-full/')
        ->and($borderFallbackCount)->toBe(2)
        ->and($backgroundFallbackCount)->toBe(2)
        ->and($textFallbackCount)->toBe(2);
});
