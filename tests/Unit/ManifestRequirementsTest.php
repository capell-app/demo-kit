<?php

declare(strict_types=1);

use Capell\DemoKit\Actions\RedactDemoKitErrorMessageAction;
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
                'quick',
                'reset',
                'allow-production',
                'force',
            ]);
    });
});

it('redacts configured archive secrets from demo kit error messages', function (): void {
    config()->set('capell-demo-kit.archive.url', 'https://archives.example.test/demo.zip?token=archive-token-secret');
    config()->set('capell-demo-kit.archive.checksum', 'archive-checksum-secret');

    $message = RedactDemoKitErrorMessageAction::run(
        'GET https://archives.example.test/demo.zip?token=archive-token-secret failed with Authorization: Bearer bearer-token-secret and checksum archive-checksum-secret',
    );

    expect($message)->toContain('GET [redacted]')
        ->and($message)->toContain('Authorization: Bearer [redacted]')
        ->and($message)->not->toContain('archive-token-secret')
        ->and($message)->not->toContain('bearer-token-secret')
        ->and($message)->not->toContain('archive-checksum-secret');
});
