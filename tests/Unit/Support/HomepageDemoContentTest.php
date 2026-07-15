<?php

declare(strict_types=1);

use Capell\DemoKit\Support\HomepageDemoContent;

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

it('keeps the demo hero background selector aligned with hero package markup', function (): void {
    $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/components/widget/homepage-section.blade.php');

    expect($view)
        ->toContain('.widget-capell-home-hero-command-center .hero-background,')
        ->toContain('.widget-capell-home-hero-command-center .capell-hero-background {')
        ->toContain('$heroHighlights = $homepageItems(\'highlights\');');
});

it('keeps hero proof points token driven and above decorative layers', function (): void {
    $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/components/widget/homepage-section.blade.php');
    $borderFallbackCount = preg_match_all('/border-color:\s*#[0-9a-f]{6};\s*border-color:\s*color-mix\(/i', $view);
    $backgroundFallbackCount = preg_match_all('/background:\s*#[0-9a-f]{6};\s*background:\s*color-mix\(/i', $view);
    $textFallbackCount = preg_match_all('/color:\s*#[0-9a-f]{6};\s*color:\s*color-mix\(/i', $view);

    expect($view)
        ->toContain('.capell-home-hero-highlight {')
        ->toContain('var(--theme-primary, #315f8f)')
        ->toContain('var(--theme-surface, #ffffff)')
        ->toContain('var(--theme-foreground, #1a1c1b)')
        ->toContain('.dark .capell-home-hero-highlight {')
        ->toContain('class="capell-home-hero-grid relative z-10"')
        ->toContain('class="capell-home-hero-copy grid gap-6"')
        ->toContain('class="capell-home-hero-highlight rounded-full')
        ->and($borderFallbackCount)->toBe(2)
        ->and($backgroundFallbackCount)->toBe(2)
        ->and($textFallbackCount)->toBe(2);
});
