<?php

declare(strict_types=1);

use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Support\CapellLayoutBuilderManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

beforeEach(function (): void {
    foreach (CapellLayoutBuilderManager::getMigrations() as $migration) {
        $instance = include dirname(__DIR__, 4) . '/layout-builder/database/migrations/' . $migration . '.php';

        $instance->up();
    }

    LayoutBuilderInstallPackageAction::run();
    resolve(BlueprintCreator::class)->createPageTypes();

    app()->bind(DemoCreator::class, function (Application $application, array $parameters): DemoCreator {
        $creator = Mockery::mock(DemoCreator::class . '[createPage,refreshDemoPage]', [$parameters['url']]);
        $creator->shouldReceive('createPage')
            ->andReturnUsing(function (
                array $data,
                Site $site,
                EloquentCollection $languages,
                ?Page $parent = null,
            ): Page {
                $pageType = Blueprint::query()->pageType()->default()->firstOrFail();

                return Page::factory()
                    ->site($site)
                    ->type($pageType)
                    ->withTranslations($languages, ['title' => $data['name']['en']])
                    ->create([
                        'name' => $data['name']['en'],
                        'parent_id' => $parent?->getKey(),
                    ]);
            });
        $creator->shouldReceive('refreshDemoPage')
            ->andReturnUsing(function (Page $page, EloquentCollection $languages, bool $refreshUrls = true): Page {
                $page->translations()->updateOrCreate(
                    ['language_id' => $languages->firstOrFail()->getKey()],
                    ['title' => $page->name, 'content' => '<p>Refreshed</p>'],
                );

                return $page->refresh();
            });

        return $creator;
    });
});

it('refreshes the Stitch demo pages', function (): void {
    $language = Language::factory()->english()->create();
    Site::factory()->default()->language($language)->withTranslations($language)->create(['name' => 'Default Site']);

    capell_artisan('capell:demo-kit-refresh-stitch-pages', ['--force' => true])->assertExitCode(0);

    expect(Page::query()->where('name', 'Contact')->exists())->toBeTrue();
});

it('refuses to run in the production environment without an override', function (): void {
    $originalEnvironment = app()->make('env');
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        $language = Language::factory()->english()->create();
        Site::factory()->default()->language($language)->withTranslations($language)->create(['name' => 'Default Site']);

        capell_artisan('capell:demo-kit-refresh-stitch-pages', ['--force' => true])->assertExitCode(1);

        expect(Page::query()->where('name', 'Contact')->exists())->toBeFalse();
    } finally {
        app()->detectEnvironment(static fn (): string => is_string($originalEnvironment) ? $originalEnvironment : 'testing');
    }
});

it('runs in production when --allow-production is supplied', function (): void {
    $originalEnvironment = app()->make('env');
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        $language = Language::factory()->english()->create();
        Site::factory()->default()->language($language)->withTranslations($language)->create(['name' => 'Default Site']);

        capell_artisan('capell:demo-kit-refresh-stitch-pages', ['--force' => true, '--allow-production' => true])->assertExitCode(0);

        expect(Page::query()->where('name', 'Contact')->exists())->toBeTrue();
    } finally {
        app()->detectEnvironment(static fn (): string => is_string($originalEnvironment) ? $originalEnvironment : 'testing');
    }
});
