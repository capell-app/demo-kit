<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support;

final class HomepageDemoContent
{
    /**
     * @return array<string, mixed>
     */
    public static function forWidget(string $key): array
    {
        return self::all()[$key] ?? [];
    }

    /**
     * @param  array<array-key, mixed>|null  $content
     * @return array<string, mixed>
     */
    public static function mergeForWidget(string $key, ?array $content): array
    {
        return self::merge(self::forWidget($key), is_array($content) ? $content : []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'capell-home-hero-command-center' => [
                'eyebrow' => 'Capell CMS',
                'heading' => 'Build Laravel CMS sites without losing control',
                'copy' => 'Model content, compose layouts, install packages, preview releases, and ship cached public pages from one Laravel-native system.',
                'highlights' => [
                    'Typed page trees',
                    'Editor-owned layouts',
                    'Package-safe widgets',
                    'Static cache checks',
                ],
                'primary_label' => 'Explore the demo',
                'primary_url' => '/resources',
                'secondary_label' => 'View pricing',
                'secondary_url' => '/pricing',
                'system_board_label' => 'Capell system board',
                'pagination_label' => 'Capell system board pagination',
                'slides' => HomepageDemoImages::heroSlides(),
            ],
            'capell-home-proof-strip' => [
                'label' => 'Demo proof points',
                'metrics' => [
                    ['value' => '38', 'label' => 'packages installed'],
                    ['value' => '7', 'label' => 'custom homepage widgets'],
                    ['value' => '120+', 'label' => 'static pages generated'],
                    ['value' => '4', 'label' => 'discovery checks'],
                ],
            ],
            'capell-home-demo-showcase' => [
                'eyebrow' => 'What ships in the demo',
                'heading' => 'Custom layouts that prove the CMS can change shape',
                'copy' => 'Each homepage region uses a different composition so the demo feels like a real system, not a repeated stack of generic cards.',
                'image_alt' => 'Capell demo workspace preview',
                'cards' => [
                    [
                        'eyebrow' => 'Editorial command center',
                        'title' => 'Operational content, not placeholder widgets',
                        'copy' => 'Use widget translations, page types, layout containers, and package data to show how an editor-owned surface stays structured.',
                    ],
                    [
                        'eyebrow' => 'Package marketplace',
                        'title' => 'Extension evidence grid',
                        'badges' => ['SEO Suite', 'Search', 'Forms', 'Access Gate', 'Newsletter', 'Insights'],
                    ],
                    [
                        'eyebrow' => 'Publishing workflow',
                        'title' => 'Timeline plus checklist',
                        'steps' => [
                            ['title' => 'Model', 'copy' => 'Types and widgets'],
                            ['title' => 'Compose', 'copy' => 'Layout containers'],
                            ['title' => 'Release', 'copy' => 'Cache and sitemap'],
                        ],
                    ],
                ],
            ],
            'capell-home-demo-widgets-carousel' => [
                'eyebrow' => 'Demo widgets',
                'heading' => 'Small interactive widgets that feel like a real CMS',
                'copy' => 'These package-owned widgets fill out the homepage with concrete CMS behaviours while keeping the public frontend static, inspectable, and safe.',
                'state_label' => 'Demo state',
                'previous_label' => 'Previous demo widgets',
                'next_label' => 'Next demo widgets',
                'pages_label' => 'Demo widget carousel pages',
                'page_button_label' => 'Show demo widget set',
                'items' => [
                    ['code' => 'WF', 'label' => 'Workflow', 'title' => 'Editorial workflow', 'description' => 'Draft, review, preview, approve, and publish from one traceable content queue.', 'metric' => '5 states'],
                    ['code' => 'TH', 'label' => 'Theme', 'title' => 'Theme controls', 'description' => 'Expose colors, spacing, navigation, and footer settings without leaking admin data.', 'metric' => '12 tokens'],
                    ['code' => 'CL', 'label' => 'Library', 'title' => 'Content library', 'description' => 'Reusable sections and typed widgets keep page building consistent across sites.', 'metric' => '34 widgets'],
                    ['code' => 'SI', 'label' => 'Insights', 'title' => 'Search insights', 'description' => 'Show what visitors search for and which pages need better content coverage.', 'metric' => '8 queries'],
                    ['code' => 'NL', 'label' => 'Newsletter', 'title' => 'Newsletter capture', 'description' => 'Place package-owned signup widgets into layouts with clear consent copy.', 'metric' => '3 lists'],
                    ['code' => 'RC', 'label' => 'Release', 'title' => 'Release checklist', 'description' => 'Verify cache, sitemap, assets, forms, and public output before handover.', 'metric' => '9 checks'],
                    ['code' => 'MA', 'label' => 'Media', 'title' => 'Media automation', 'description' => 'Generated conversions and alt text prompts keep image-heavy pages maintainable.', 'metric' => '4 sizes'],
                    ['code' => 'TR', 'label' => 'Locales', 'title' => 'Translation queue', 'description' => 'Track localized content coverage without changing the public rendering contract.', 'metric' => '6 locales'],
                ],
            ],
            'capell-extension-marketplace-showcase' => [
                'eyebrow' => 'Marketplace extensions',
                'heading' => 'Extension pages that help teams decide',
                'copy' => 'Extension detail pages show the contract behind each package: install eligibility, licence state, surfaces, dependencies, frontend budget, health status, documentation, feedback controls, and screenshot galleries.',
                'image_alt' => 'Capell marketplace screenshot preview',
                'cards' => [
                    ['title' => 'See the product before installing', 'copy' => 'Large screenshots make admin pages, frontend components, settings screens, and workflows visible without leaving Capell.'],
                    ['title' => 'Keep extension boundaries explicit', 'copy' => 'Surfaces, dependencies, contribution counts, and performance budgets tell developers what the extension adds.'],
                    ['title' => 'Connect docs to the buying decision', 'copy' => 'Public and entitled documentation sit beside licence status, access checks, version history, and Marketplace actions.'],
                ],
            ],
            'capell-home-technical-pipeline' => [
                'eyebrow' => 'Release path',
                'heading' => 'From admin edits to verified frontend',
                'copy' => 'Capell keeps the editable CMS surface and the generated public output connected through explicit ownership and checks.',
                'steps' => [
                    ['number' => '01', 'title' => 'Model content', 'copy' => 'Define typed pages, widgets, translations, media, and package fields.'],
                    ['number' => '02', 'title' => 'Compose layout', 'copy' => 'Place widgets into containers that the public theme renders predictably.'],
                    ['number' => '03', 'title' => 'Publish safely', 'copy' => 'Preview changes, approve releases, warm cache, and generate static HTML.'],
                    ['number' => '04', 'title' => 'Verify output', 'copy' => 'Run doctor, discovery, sitemap, and runtime asset checks before handover.'],
                ],
            ],
            'capell-home-route-split' => [
                'items' => [
                    ['eyebrow' => 'Resources hub', 'title' => 'Technical guides and launch checklists', 'cta' => 'Read the CMS playbook', 'url' => '/resources'],
                    ['eyebrow' => 'Pricing', 'title' => 'Licensing and support for production teams', 'cta' => 'Plan the rollout', 'url' => '/pricing'],
                    ['eyebrow' => 'Contact', 'title' => 'Architecture, migration, and package support', 'cta' => 'Start scoping', 'url' => '/contact#scoping'],
                ],
            ],
            'capell-home-final-cta' => [
                'eyebrow' => 'Demo install',
                'heading' => 'Show a CMS that feels assembled, verified, and ready to extend.',
                'copy' => 'The homepage demonstrates layout shapes, custom widget compositions, package boundaries, and public-page discovery paths.',
                'action_label' => 'Start implementation scoping',
                'action_url' => '/contact#scoping',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<array-key, mixed>  $content
     * @return array<string, mixed>
     */
    private static function merge(array $defaults, array $content): array
    {
        foreach ($content as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                $defaults[$key] = $value;

                continue;
            }

            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && ! array_is_list($defaults[$key])) {
                $defaults[$key] = self::merge($defaults[$key], $value);

                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
