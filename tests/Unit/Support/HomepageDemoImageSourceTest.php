<?php

declare(strict_types=1);

it('keeps homepage demo imagery as editable URL image sources', function (): void {
    $creator = file_get_contents(dirname(__DIR__, 3) . '/src/Support/Creator/HomepageDemoBlockCreator.php');
    $view = file_get_contents(dirname(__DIR__, 3) . '/resources/views/components/block/homepage-section.blade.php');

    expect($creator)->toContain('images.unsplash.com')
        ->and($creator)->toContain("'type' => 'url'")
        ->and($creator)->toContain("'url' => \$url")
        ->and($view)->toContain('<x-capell::image-source')
        ->and($view)->toContain("\$block->getMeta('image_source')");
});
