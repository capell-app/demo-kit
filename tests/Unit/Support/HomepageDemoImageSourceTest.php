<?php

declare(strict_types=1);

it('keeps homepage demo imagery as editable URL image sources', function (): void {
    $creator = file_get_contents(dirname(__DIR__, 3) . '/src/Support/Creator/HomepageDemoBlockCreator.php');
    $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/components/block/homepage-section.blade.php');

    expect($creator)->toContain('images.unsplash.com')
        ->and($creator)->toContain("'type' => 'url'")
        ->and($creator)->toContain("'url' => \$url")
        ->and($creator)->toContain('HOMEPAGE_HERO_SLIDES')
        ->and($creator)->toContain("\$meta['hero_slides'] = self::HOMEPAGE_HERO_SLIDES")
        ->and($view)->toContain('<x-capell::image-source')
        ->and($view)->toContain("\$block->getMeta('image_source')")
        ->and($view)->toContain('data-carousel-autoplay="1"')
        ->and($view)->toContain('data-carousel-loop="0"')
        ->and($view)->toContain('data-carousel-pause-on-hover="0"')
        ->and($view)->toContain('data-carousel-rewind="1"')
        ->and($view)->toContain('data-carousel-controls="{{ $heroCarouselId }}"')
        ->and($view)->toContain('capellHomeHeroProgress')
        ->and($view)->toContain('opacity: 0.55')
        ->and($view)->toContain('rgb(49 95 143 / 0.38)')
        ->and($view)->not->toContain('capellHomeHeroSpin');
});
