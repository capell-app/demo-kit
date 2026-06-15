<?php

declare(strict_types=1);

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Facades\CapellCore;
use Capell\DemoKit\Actions\RedactDemoKitErrorMessageAction;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
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
                'packages',
                'theme',
                'seed',
                'quick',
                'reset',
                'skip-demo-users',
                'allow-production',
                'force',
            ]);
    });

    it('keeps runtime package demo params aligned with the manifest', function () use ($demoKitManifest): void {
        $manifest = $demoKitManifest();
        $registeredPackage = CapellCore::getPackage(DemoKitServiceProvider::$packageName);

        expect($registeredPackage->demoCommand)->toBe($manifest['commands']['demo'])
            ->and($registeredPackage->demoParams)->toBe($manifest['commands']['demoParams']);
    });

    it('declares concrete manifest contribution classes for shipped extension surfaces', function () use ($demoKitManifest): void {
        $manifest = $demoKitManifest();

        expect($manifest['surfaces'])->toBe(['admin', 'frontend', 'console'])
            ->and($manifest['contributionTraceability']['deferredContributions'])->toBe([])
            ->and($manifest['contributes'])->not->toBeEmpty();

        $contributionTypes = collect($manifest['contributes'])->pluck('type')->all();

        expect($contributionTypes)->toBe([
            'admin-page',
            'configurator',
            'asset',
            'frontend-component',
            'dashboard-widget',
            'console-command',
            'health-check',
        ]);

        collect($manifest['contributes'])
            ->pluck('class')
            ->each(function (string $class): void {
                expect(class_exists($class))->toBeTrue()
                    ->and(is_subclass_of($class, ExtensionContribution::class))->toBeTrue()
                    ->and($class::compatibleCapellApiVersion())->toBe('^4.0');
            });
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
