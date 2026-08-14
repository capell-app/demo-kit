<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support;

final class HomepageDemoImages
{
    private const string ASSET_DIRECTORY = 'vendor/capell-demo-kit/images';

    /** @var array<string, string> */
    private const array WIDGET_FILES = [
        'capell-home-hero-command-center' => 'workspace-3f622651c9b7.jpg',
        'capell-home-demo-showcase' => 'packages-f3f598670664.jpg',
        'capell-extension-marketplace-showcase' => 'workflow-8a21722f8d5f.jpg',
    ];

    /** @return array{type: string, url: string}|null */
    public static function sourceForWidget(string $key): ?array
    {
        $filename = self::WIDGET_FILES[$key] ?? null;

        return is_string($filename) ? self::source($filename) : null;
    }

    /** @return list<array{image: array{type: string, url: string}, alt: string, label: string, value: string, status: string}> */
    public static function heroSlides(): array
    {
        return [
            [
                'image' => self::source(self::WIDGET_FILES['capell-home-hero-command-center']),
                'alt' => 'Capell CMS workspace preview',
                'label' => 'Page types',
                'value' => 'Home, Resources, Services',
                'status' => 'Typed',
            ],
            [
                'image' => self::source(self::WIDGET_FILES['capell-home-demo-showcase']),
                'alt' => 'Capell content package dashboard preview',
                'label' => 'Packages',
                'value' => 'Layout Builder, SEO, Search, Publishing',
                'status' => 'Installed',
            ],
            [
                'image' => self::source(self::WIDGET_FILES['capell-extension-marketplace-showcase']),
                'alt' => 'Capell publishing workflow preview',
                'label' => 'Workflow',
                'value' => 'Draft, preview, approve, publish',
                'status' => 'Traceable',
            ],
        ];
    }

    /** @return array{type: string, url: string} */
    private static function source(string $filename): array
    {
        $assetUrl = config('app.asset_url');
        $assetPrefix = is_string($assetUrl) ? parse_url($assetUrl, PHP_URL_PATH) : null;
        $assetPrefix = is_string($assetPrefix) ? trim($assetPrefix, '/') : '';
        $assetPrefix = $assetPrefix === '' ? '' : '/' . $assetPrefix;

        return [
            'type' => 'url',
            'url' => $assetPrefix . '/' . self::ASSET_DIRECTORY . '/' . $filename,
        ];
    }
}
