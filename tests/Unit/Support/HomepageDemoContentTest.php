<?php

declare(strict_types=1);

use Capell\DemoKit\Support\HomepageDemoContent;

it('ships concise homepage hero proof points', function (): void {
    $content = HomepageDemoContent::forBlock('capell-home-hero-command-center');

    expect($content['heading'] ?? null)->toBe('Build Laravel CMS sites without losing control')
        ->and($content['copy'] ?? null)->toBe('Model content, compose layouts, install packages, preview releases, and ship cached public pages from one Laravel-native system.')
        ->and($content['highlights'] ?? null)->toBe([
            'Typed page trees',
            'Editor-owned layouts',
            'Package-safe widgets',
            'Static cache checks',
        ]);

    $merged = HomepageDemoContent::mergeForBlock('capell-home-hero-command-center', [
        'highlights' => ['Custom proof point'],
    ]);

    expect($merged['highlights'])->toBe(['Custom proof point']);
});

it('keeps the demo hero background selector aligned with hero package markup', function (): void {
    $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/components/block/homepage-section.blade.php');

    expect($view)
        ->toContain('.block-capell-home-hero-command-center .hero-background,')
        ->toContain('.block-capell-home-hero-command-center .capell-hero-background {')
        ->toContain('$heroHighlights = $homepageItems(\'highlights\');');
});
