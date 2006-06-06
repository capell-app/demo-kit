<?php

declare(strict_types=1);

namespace Capell\DemoKit\Console\Commands;

use Capell\DemoKit\Actions\PrepareDemoKitScreenshotFixtureAction;
use Capell\DemoKit\Actions\RestoreDemoKitScreenshotFixtureAction;
use Capell\DemoKit\Data\DemoKitScreenshotFixtureData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

final class DemoKitScreenshotFixtureCommand extends Command
{
    protected $signature = 'capell:demo-kit-screenshot-fixture
        {--state= : Fixture state to prepare or restore}
        {--attempt-token= : Explicit disposable attempt token}
        {--restore : Restore rows owned by the attempt token}
        {--force : Confirm an intentional disposable screenshot fixture operation}
        {--json : Output a machine-readable result}';

    protected $description = 'Prepare or restore an owned Demo Kit screenshot fixture.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            return $this->failScreenshotFixture('Refusing to operate on screenshot fixtures without --force.');
        }

        $state = $this->scalarOption('state');
        $attemptToken = $this->scalarOption('attempt-token');

        if ($state === null || $attemptToken === null) {
            return $this->failScreenshotFixture('Screenshot fixtures require --state and --attempt-token.');
        }

        try {
            $result = retry(
                12,
                fn (): DemoKitScreenshotFixtureData => $this->option('restore')
                    ? RestoreDemoKitScreenshotFixtureAction::run($state, $attemptToken)
                    : PrepareDemoKitScreenshotFixtureAction::run($state, $attemptToken),
                500,
                fn (Throwable $exception): bool => $this->isTransientSqliteLock($exception),
            );

            if ($this->option('json')) {
                $this->line(json_encode($result->toArray(), JSON_THROW_ON_ERROR));
            } else {
                $this->info(sprintf('Demo Kit screenshot fixture %s: %s.', $result->operation, $result->state));
            }

            return CommandAlias::SUCCESS;
        } catch (Throwable $exception) {
            return $this->failScreenshotFixture($exception->getMessage());
        }
    }

    private function scalarOption(string $key): ?string
    {
        $value = $this->option($key);

        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return (string) $value;
    }

    private function isTransientSqliteLock(Throwable $exception): bool
    {
        return DB::connection()->getDriverName() === 'sqlite'
            && str_contains(strtolower($exception->getMessage()), 'database is locked');
    }

    private function failScreenshotFixture(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['error' => $message], JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return CommandAlias::FAILURE;
    }
}
