<?php

declare(strict_types=1);

use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\DemoKit\Support\HomepageDemoContent;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Illuminate\Support\Facades\File;

require_once __DIR__ . '/../../Support/HomepageRender.php';

it('keeps homepage demo imagery editable while serving it from package assets', function (): void {
    config(['app.asset_url' => 'https://demo.capell.test/versions/latest']);

    // Required hooks and values protect the visual contract independently of formatting.
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

    resolve(TypeCreator::class)->createWidgetTypes();
    $widget = resolve(DemoCreator::class)->createHomepageHeroCommandCenterWidget();
    $html = renderHomepageWidget($widget);
    expect($urls)->toHaveCount(3)
        ->and(data_get($widget->meta, 'image_source.type'))->toBe('url')
        ->and(domQueryCount($html, '//*[@data-carousel-autoplay="1"][@data-carousel-loop="0"][@data-carousel-pause-on-hover="0"][@data-carousel-rewind="1"]'))->toBe(1)
        ->and(domQueryCount($html, '//*[@data-carousel-controls = //*[@data-carousel-id]/@data-carousel-id]'))->toBeGreaterThan(0);
    $widget = resolve(DemoCreator::class)->createHomepageDemoShowcaseWidget();
    $meta = is_array($widget->meta) ? $widget->meta : [];
    $meta['image_source'] = ['type' => 'url', 'url' => '/editor-image.jpg'];
    $widget->forceFill(['meta' => $meta]);
    $edited = renderHomepageWidget($widget);
    expect(domQueryCount($edited, '//img[@src="/editor-image.jpg"]'))->toBeGreaterThan(0);
    expect($view)
        ->toContain('capellHomeHeroProgress')
        ->and($view)->toMatch('/opacity\s*:\s*0\.55/')
        ->and($view)->toMatch('/rgb\s*\(\s*49\s+95\s+143\s+\/\s+0\.38\s*\)\s*/')
        ->and($view)->not->toContain('capellHomeHeroSpin');

    foreach ($urls as $url) {
        expect($url)->toStartWith('/versions/latest/')
            ->and($url)->toContain('/vendor/capell-demo-kit/images/')
            ->and($url)->not->toContain('images.unsplash.com')
            ->and($url)->toMatch('#/vendor/capell-demo-kit/images/[a-z]+-[a-f0-9]{12}\\.jpg$#')
            ->and(dirname(__DIR__, 3) . '/resources/dist/images/' . basename($url))->toBeFile();
    }
});

it('publishes the demo image URLs through the public assets command', function (): void {
    $directory = sys_get_temp_dir() . '/demo-images-' . bin2hex(random_bytes(8));
    $previousPublicPath = public_path();
    app()->usePublicPath($directory);
    try {
        app()->register(DemoKitServiceProvider::class, true);
        $this->artisan('vendor:publish', ['--provider' => DemoKitServiceProvider::class, '--tag' => 'laravel-assets', '--force' => true])->assertSuccessful();
        foreach (glob(dirname(__DIR__, 3) . '/resources/dist/images/*.jpg') ?: [] as $source) {
            $published = $directory . '/vendor/capell-demo-kit/images/' . basename($source);
            expect($published)->toBeFile()
                ->and(hash_file('sha256', $published))->toBe(hash_file('sha256', $source));
        }
    } finally {
        app()->usePublicPath($previousPublicPath);
        File::deleteDirectory($directory);
    }
});
