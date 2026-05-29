<?php

declare(strict_types=1);

use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\DemoKit\Actions\RefreshDemoStitchPagesAction;
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
        expect($parameters['url'])->toBe(config('app.url'));

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
                expect($refreshUrls)->toBeFalse();

                $page->translations()->updateOrCreate(
                    ['language_id' => $languages->firstOrFail()->getKey()],
                    ['title' => $page->name, 'content' => '<p>Refreshed</p>'],
                );

                return $page->refresh();
            });

        return $creator;
    });
});

it('requires force confirmation before refreshing stitch demo pages', function (): void {
    expect(fn (): mixed => RefreshDemoStitchPagesAction::run(force: false))
        ->toThrow(InvalidArgumentException::class, 'requires force confirmation');
});

it('refreshes the default site pages and repairs child parentage', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create(['name' => 'Default Site']);
    $wrongParent = Page::factory()->site($site)->withTranslations($language)->create(['name' => 'Wrong Parent']);
    $implementation = Page::factory()
        ->site($site)
        ->parent($wrongParent)
        ->withTranslations($language)
        ->create(['name' => 'Implementation']);

    $pages = RefreshDemoStitchPagesAction::run(force: true);

    expect($pages)->toHaveCount(23)
        ->and(Page::query()->where('site_id', $site->getKey())->where('name', 'Contact')->exists())->toBeTrue()
        ->and($implementation->refresh()->parent?->name)->toBe('Pricing')
        ->and($pages->pluck('name'))->toContain('Implementation', 'Compliance', 'Sustainability');
});

it('uses requested site and language filters and rejects ambiguous sites', function (): void {
    $english = Language::factory()->english()->create();
    $french = Language::factory()->create(['code' => 'fr', 'name' => 'French', 'default' => false]);
    $site = Site::factory()->language($english)->withTranslations([$english, $french])->create(['name' => 'Named Site']);
    Site::factory()->language($english)->withTranslations($english)->create(['name' => 'Named Site']);

    expect(fn (): mixed => RefreshDemoStitchPagesAction::run('Named Site', force: true))
        ->toThrow(InvalidArgumentException::class, 'Multiple sites were found');

    $site->forceFill(['name' => 'Unique Site'])->save();

    $pages = RefreshDemoStitchPagesAction::run('Unique Site', 'fr', true);

    expect($pages)->toHaveCount(23)
        ->and($pages->firstOrFail()->translations()->pluck('language_id')->all())->toContain($french->getKey());
});

it('deactivates duplicate active urls left behind by refreshed pages', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->default()->language($language)->withTranslations($language)->create();
    $pageType = Blueprint::query()->pageType()->default()->firstOrFail();
    $contact = Page::factory()->site($site)->type($pageType)->withTranslations($language)->create(['name' => 'Contact']);
    $duplicate = Page::factory()->site($site)->type($pageType)->withTranslations($language)->create(['name' => 'Duplicate Contact']);

    PageUrl::query()->create([
        'site_id' => $site->getKey(),
        'language_id' => $language->getKey(),
        'pageable_type' => $contact->getMorphClass(),
        'pageable_id' => $contact->getKey(),
        'url' => '/contact',
        'status' => true,
    ]);
    $duplicateUrl = PageUrl::query()->create([
        'site_id' => $site->getKey(),
        'language_id' => $language->getKey(),
        'pageable_type' => $duplicate->getMorphClass(),
        'pageable_id' => $duplicate->getKey(),
        'url' => '/contact',
        'status' => true,
    ]);

    RefreshDemoStitchPagesAction::run(force: true);

    expect($duplicateUrl->refresh()->status)->toBeFalse();
});

it('returns command failures for invalid refresh options and success with force', function (): void {
    test()->artisan('capell:demo-kit-refresh-stitch-pages')
        ->expectsOutput('Refreshing Stitch demo pages requires force confirmation.')
        ->assertExitCode(1);

    $language = Language::factory()->english()->create();
    Site::factory()->default()->language($language)->withTranslations($language)->create(['name' => 'Command Site']);

    test()->artisan('capell:demo-kit-refresh-stitch-pages', [
        '--site' => 'Command Site',
        '--force' => true,
    ])
        ->expectsOutput('Refreshed 23 Stitch demo pages.')
        ->assertExitCode(0);
});
