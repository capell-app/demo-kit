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
                'adopt-existing-site',
                'skip-package-demos',
                'force',
            ]);
    });

    it('keeps runtime package demo params aligned with the manifest', function () use ($demoKitManifest): void {
        $manifest = $demoKitManifest();
        $registeredPackage = CapellCore::getPackage(DemoKitServiceProvider::$packageName);

        expect($registeredPackage->demoCommand)->toBe($manifest['commands']['demo'])
            ->and($registeredPackage->demoParams)->toBe($manifest['commands']['demoParams']);
    });

    it('declares its registered package migration and owned table', function () use ($demoKitManifest): void {
        $manifest = $demoKitManifest();
        $migrationFiles = glob(__DIR__ . '/../../database/migrations/*.php') ?: [];

        expect(data_get($manifest, 'database.migrations'))->toBeTrue()
            ->and(data_get($manifest, 'database.requiredTables'))->toBe([
                'capell_demo_kit_generation_runs',
            ])
            ->and(array_map(
                static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
                $migrationFiles,
            ))->toBe([
                '2026_07_19_130000_create_demo_kit_generation_runs_table',
            ]);
    });

    it('keeps the showcase frontend output cacheable with invalidation metadata', function () use ($demoKitManifest): void {
        // Demo Kit's public showcase output is cache-safe, so it must not veto
        // the origin HTML cache. The cacheable flag has to agree with the
        // public-output contract and declare invalidation sources, or the
        // manifest validator rejects it and the whole site stops caching.
        $manifest = $demoKitManifest();
        $cacheSafety = $manifest['performance']['cacheSafety'] ?? null;

        throw_unless(is_array($cacheSafety), RuntimeException::class, 'Expected Demo Kit cacheSafety metadata array.');

        expect($cacheSafety['cacheable'])->toBeTrue()
            ->and($cacheSafety['sensitiveOutput'])->toBeFalse()
            ->and($manifest['security']['publicOutput']['cacheSafe'])->toBeTrue()
            ->and($cacheSafety['invalidationSources'])->not->toBeEmpty();

        $invalidationSources = $cacheSafety['invalidationSources'] ?? [];
        throw_unless(is_array($invalidationSources), RuntimeException::class, 'Expected Demo Kit invalidation sources array.');

        $invalidationModels = collect($invalidationSources)->pluck('model')->all();

        expect($invalidationModels)->toContain(
            'Capell\\Core\\Models\\Page',
            'Capell\\Core\\Models\\PageUrl',
        );
    });

    it('declares concrete manifest contribution classes for shipped extension surfaces', function () use ($demoKitManifest): void {
        $manifest = $demoKitManifest();
        $contributes = $manifest['contributes'] ?? [];

        throw_unless(is_array($contributes), RuntimeException::class, 'Demo Kit contributions must be an array.');

        expect($manifest['surfaces'])->toBe(['admin', 'frontend', 'console'])
            ->and($manifest['contributionTraceability']['deferredContributions'])->toBe([])
            ->and($contributes)->not->toBeEmpty();

        $contributionTypes = collect($contributes)->pluck('type')->all();

        expect($contributionTypes)->toBe([
            'admin-page',
            'configurator',
            'asset',
            'frontend-component',
            'dashboard-widget',
            'console-command',
            'health-check',
        ]);

        collect($contributes)
            ->pluck('class')
            ->each(function (mixed $class): void {
                throw_unless(is_string($class), RuntimeException::class, 'Demo Kit contribution class must be a string.');

                $compatibleVersion = $class::compatibleCapellApiVersion();

                throw_unless(is_string($compatibleVersion), RuntimeException::class, 'Demo Kit contribution API version must be a string.');

                expect(class_exists($class))->toBeTrue();
                expect(is_subclass_of($class, ExtensionContribution::class))->toBeTrue();
                expect($compatibleVersion)->toBe('^1.0');
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
