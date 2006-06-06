<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support;

use RuntimeException;

final class DemoKitScreenshotFixtureGuard
{
    public const string PACKAGE = 'capell-app/demo-kit';

    public const string MARKER_KEY = 'screenshot_fixture';

    /**
     * @var list<string>
     */
    private const array STATES = [
        'reuse-review',
        'blocked-review',
        'queue-confirmation',
        'queued',
        'running',
        'completed',
        'failed',
        'stalled',
        'reset-confirmation',
        'reset-completed',
    ];

    public static function assertEnvironment(): void
    {
        $environment = app()->bound('config') ? config('app.env') : getenv('APP_ENV');
        $configuredFixture = getenv('CAPELL_SCREENSHOT_FIXTURE');
        $configuredAppPath = getenv('CAPELL_SCREENSHOT_APP_PATH');
        $basePath = realpath(base_path());
        $appPath = is_string($configuredAppPath) ? realpath($configuredAppPath) : false;

        throw_unless(
            in_array($environment, ['local', 'testing'], true)
                && in_array($configuredFixture, ['1', 'true', 'record-state'], true)
                && is_string($basePath)
                && is_string($appPath)
                && $basePath === $appPath,
            RuntimeException::class,
            'Demo Kit screenshot fixtures require the explicit disposable local screenshot environment.',
        );
    }

    public static function state(string $state): string
    {
        $state = trim($state);

        throw_unless(
            in_array($state, self::STATES, true),
            RuntimeException::class,
            sprintf('Unsupported Demo Kit screenshot fixture state [%s].', $state),
        );

        return $state;
    }

    public static function attemptToken(string $attemptToken): string
    {
        $attemptToken = trim($attemptToken);

        throw_unless(
            preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{7,127}\z/D', $attemptToken) === 1,
            RuntimeException::class,
            'Demo Kit screenshot fixtures require an explicit attempt token.',
        );

        return $attemptToken;
    }

    /**
     * @return array{package: string, attempt_token: string, state: string, role: string, row_id: int}
     */
    public static function marker(string $state, string $attemptToken, string $role, int $rowId): array
    {
        return [
            'package' => self::PACKAGE,
            'attempt_token' => $attemptToken,
            'state' => $state,
            'role' => $role,
            'row_id' => $rowId,
        ];
    }

    /**
     * @return array{package: string, attempt_token: string, state: string, role: string, row_id: int}|null
     */
    public static function readMarker(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        foreach (['package', 'attempt_token', 'state', 'role'] as $key) {
            if (! is_string($value[$key] ?? null)) {
                return null;
            }
        }

        if (! is_int($value['row_id'] ?? null)) {
            return null;
        }

        return [
            'package' => $value['package'],
            'attempt_token' => $value['attempt_token'],
            'state' => $value['state'],
            'role' => $value['role'],
            'row_id' => $value['row_id'],
        ];
    }

    /**
     * @param  array{package: string, attempt_token: string, state: string, role: string, row_id: int}|null  $marker
     */
    public static function owns(?array $marker, string $state, string $attemptToken, string $role): bool
    {
        return $marker !== null
            && $marker['package'] === self::PACKAGE
            && $marker['attempt_token'] === $attemptToken
            && $marker['state'] === $state
            && $marker['role'] === $role;
    }
}
