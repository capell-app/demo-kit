<?php

declare(strict_types=1);

use Capell\Core\Support\Renderables\RenderableRegistry;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Capell\Tests\Support\PackageInstallationTestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

it('activates installed runtime once after metadata has already booted', function (): void {
    PackageInstallationTestCase::assertInProcessInstallation('demo-kit', function (Application $app, Closure $refresh): void {
        $finder = $app->make(Factory::class)->getFinder();
        throw_unless($finder instanceof FileViewFinder, RuntimeException::class);
        $registry = $app->make(RenderableRegistry::class);
        expect($registry->allForType('layout-widget'))->not->toHaveKey(DemoKitServiceProvider::DemoPageContentRenderable);
        $refresh();
        expect($registry->allForType('layout-widget'))->toHaveKey(DemoKitServiceProvider::DemoPageContentRenderable);

        $schedule = $app->make(Schedule::class);
        $scheduledEvents = $schedule->events();
        $listeners = $app->make(Dispatcher::class)->getRawListeners();
        $views = $finder->getHints();
        $refresh();
        expect($app->make(Dispatcher::class)->getRawListeners())->toBe($listeners)
            ->and($finder->getHints())->toBe($views)
            ->and($schedule->events())->toBe($scheduledEvents);
    });
});
