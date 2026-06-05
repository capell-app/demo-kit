<?php

declare(strict_types=1);
use Illuminate\Support\Facades\File;

describe('demo kit capell.json manifest', function (): void {
    $demoKitManifest = fn (): array => json_decode(
        File::get(__DIR__ . '/../../capell.json'),
        associative: true,
    );

    it('registers the full demo command as the package demo command', function () use ($demoKitManifest): void {
        $manifest = $demoKitManifest();

        expect($manifest['commands']['demo'])->toBe('capell:demo-kit-full-demo')
            ->and($manifest['commands']['demoParams'])->toBe([
                'url',
                'user',
                'languages',
                'sites',
                'site-count',
                'page-count',
                'theme',
                'seed',
                'allow-production',
                'force',
            ]);
    });
});
