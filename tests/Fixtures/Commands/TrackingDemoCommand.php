<?php

declare(strict_types=1);

namespace Capell\DemoKit\Tests\Fixtures\Commands;

use Illuminate\Console\Command;

class TrackingDemoCommand extends Command
{
    /** @var list<string> */
    public static array $executionOrder = [];

    public static ?bool $queueConversionsByDefault = null;

    /** @var array<string, mixed> */
    public static array $receivedUserByCommand = [];

    /** @var array<string, mixed> */
    public static array $receivedSeedByCommand = [];

    /** @var array<string, mixed> */
    public static array $receivedLanguagesByCommand = [];

    /** @var array<string, mixed> */
    public static array $receivedSitesByCommand = [];

    public function __construct(string $signature = 'test:demo {--url=} {--user=} {--languages=*} {--sites=*} {--seed=}')
    {
        $this->signature = $signature;

        parent::__construct();
    }

    public static function reset(): void
    {
        self::$executionOrder = [];
        self::$queueConversionsByDefault = null;
        self::$receivedUserByCommand = [];
        self::$receivedSeedByCommand = [];
        self::$receivedLanguagesByCommand = [];
        self::$receivedSitesByCommand = [];
    }

    public function handle(): int
    {
        $commandName = $this->getName() ?? $this->signature;

        self::$executionOrder[] = $commandName;
        self::$queueConversionsByDefault = config('media-library.queue_conversions_by_default');

        if ($this->hasOption('user')) {
            self::$receivedUserByCommand[$commandName] = $this->option('user');
        }

        if ($this->hasOption('seed')) {
            self::$receivedSeedByCommand[$commandName] = $this->option('seed');
        }

        if ($this->hasOption('languages')) {
            self::$receivedLanguagesByCommand[$commandName] = $this->option('languages');
        }

        if ($this->hasOption('sites')) {
            self::$receivedSitesByCommand[$commandName] = $this->option('sites');
        }

        return Command::SUCCESS;
    }
}
