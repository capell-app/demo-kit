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

    public function __construct(string $signature = 'test:demo {--url=} {--user=} {--languages=*} {--sites=*}')
    {
        $this->signature = $signature;

        parent::__construct();
    }

    public static function reset(): void
    {
        self::$executionOrder = [];
        self::$queueConversionsByDefault = null;
        self::$receivedUserByCommand = [];
    }

    public function handle(): int
    {
        $commandName = $this->getName() ?? $this->signature;

        self::$executionOrder[] = $commandName;
        self::$queueConversionsByDefault = config('media-library.queue_conversions_by_default');

        if ($this->hasOption('user')) {
            self::$receivedUserByCommand[$commandName] = $this->option('user');
        }

        return Command::SUCCESS;
    }
}
