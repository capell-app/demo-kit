<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Contracts\Pageable;
use Capell\DemoKit\Data\DemoPageContentViewData;
use Capell\DemoKit\Support\DemoPageContentAssetSections;
use Capell\Frontend\Facades\Frontend;
use Capell\LayoutBuilder\Models\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildDemoPageContentViewDataAction
{
    use AsObject;

    /**
     * @param  array<string, mixed>  $widgetData
     */
    public function handle(?Pageable $pageRecord, Widget $widget, string $containerKey, array $widgetData): DemoPageContentViewData
    {
        $resolvedPageRecord = $pageRecord ?? Frontend::page();
        $pageRecord = $resolvedPageRecord instanceof Pageable ? $resolvedPageRecord : null;

        $pageName = match (Str::lower((string) ($pageRecord->name ?? ''))) {
            'faq' => 'FAQ',
            'home, buildings and architecture' => 'Home, Buildings and Architecture',
            'platform architecture' => 'Platform Architecture',
            default => (string) ($pageRecord->name ?? ''),
        };
        $pageMeta = $pageRecord instanceof Model && is_array($pageRecord->getAttribute('meta'))
            ? $pageRecord->getAttribute('meta')
            : [];
        $pageTranslation = $pageRecord instanceof Model && $pageRecord->relationLoaded('translation')
            ? $pageRecord->getRelation('translation')
            : null;
        $pageType = $pageRecord instanceof Model && $pageRecord->relationLoaded('type')
            ? $pageRecord->getRelation('type')
            : null;
        $occurrence = (int) ($widgetData['occurrence'] ?? 1);
        $assetSections = resolve(DemoPageContentAssetSections::class)->resolve($widget, $pageRecord, $containerKey, $occurrence);

        return new DemoPageContentViewData(
            pageName: $pageName,
            pageSlug: Str::slug($pageName),
            pageMeta: $pageMeta,
            hasVisibleHero: ($pageMeta['show_hero'] ?? true) !== false,
            content: $pageTranslation instanceof Model && is_string($pageTranslation->getAttribute('content'))
                ? $pageTranslation->getAttribute('content')
                : null,
            contentStructure: $pageType instanceof Model && is_string($pageType->getAttribute('content_structure'))
                ? $pageType->getAttribute('content_structure')
                : null,
            occurrence: $occurrence,
            assetSections: $assetSections,
            hasAssetSections: $pageName !== 'Blog' && $assetSections !== [],
            isContactPage: $pageName === 'Contact',
        );
    }
}
