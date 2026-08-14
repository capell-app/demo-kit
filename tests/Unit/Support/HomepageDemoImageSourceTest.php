<?php

declare(strict_types=1);

use Capell\DemoKit\Support\HomepageDemoContent;

it('keeps homepage demo imagery editable while serving it from package assets', function (): void {
    config(['app.asset_url' => 'https://demo.capell.test/versions/latest']);

    $creator = file_get_contents(dirname(__DIR__, 3) . '/src/Support/Creator/HomepageDemoWidgetCreator.php');
    $images = file_get_contents(dirname(__DIR__, 3) . '/src/Support/HomepageDemoImages.php');
    $provider = file_get_contents(dirname(__DIR__, 3) . '/src/Providers/DemoKitServiceProvider.php');
    $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/components/widget/homepage-section.blade.php');
    $content = HomepageDemoContent::forWidget('capell-home-hero-command-center');
    $slides = $content['slides'] ?? [];

    if (! is_array($slides)) {
        throw new RuntimeException('Homepage demo slides must be an array.');
    }

    $urls = [];

    foreach ($slides as $slide) {
        if (! is_array($slide)) {
            throw new RuntimeException('Each homepage demo slide must be an array.');
        }

        $image = $slide['image'] ?? null;

        if (! is_array($image) || ! is_string($image['url'] ?? null)) {
            throw new RuntimeException('Each homepage demo slide must contain a string image URL.');
        }

        $urls[] = $image['url'];
    }

    expect($creator)->not->toContain('images.unsplash.com')
        ->and($images)->toContain("'type' => 'url'")
        ->and($provider)->toContain('->hasAssets()')
        ->and($provider)->toContain("'laravel-assets'")
        ->and($urls)->toHaveCount(3)
        ->and($view)->toContain('<x-capell::image-source')
        ->and($view)->toContain("\$widget->getMeta('image_source')")
        ->and($view)->toContain('data-carousel-autoplay="1"')
        ->and($view)->toContain('data-carousel-loop="0"')
        ->and($view)->toContain('data-carousel-pause-on-hover="0"')
        ->and($view)->toContain('data-carousel-rewind="1"')
        ->and($view)->toContain('data-carousel-controls="{{ $heroCarouselId }}"')
        ->and($view)->toContain('capellHomeHeroProgress')
        ->and($view)->toContain('opacity: 0.55')
        ->and($view)->toContain('rgb(49 95 143 / 0.38)')
        ->and($view)->not->toContain('capellHomeHeroSpin');

    foreach ($urls as $url) {
        expect($url)->toStartWith('/versions/latest/')
            ->and($url)->toContain('/vendor/capell-demo-kit/images/')
            ->and($url)->not->toContain('images.unsplash.com')
            ->and($url)->toMatch('#/vendor/capell-demo-kit/images/[a-z]+-[a-f0-9]{12}\\.jpg$#')
            ->and(dirname(__DIR__, 3) . '/resources/dist/images/' . basename($url))->toBeFile();
    }
});
