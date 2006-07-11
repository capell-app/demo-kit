<?php

declare(strict_types=1);

use Capell\DemoKit\Filament\Pages\DemoKitPage;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Capell\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('reclaims an inactive run in the page journey without queueing again', function (string $status, string $entryPoint): void {
    Gate::before(fn (): bool => true);
    test()->actingAs(User::factory()->create());
    Queue::fake();
    $this->freezeTime();

    $panel = Panel::make()->id('admin')->path('admin')->default();
    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
    Filament::bootCurrentPanel();
    Filament::setServingStatus();

    $run = DemoKitGenerationRun::query()->create([
        'status' => $status,
        'parameters' => [],
    ]);

    $page = Livewire::test(DemoKitPage::class)
        ->assertSet('generationStatus', $status)
        ->assertActionDisabled('planGeneration');

    $this->travel(20)->minutes();
    $page->call('refreshGenerationRun')->assertActionDisabled('planGeneration');
    $run->touch();
    $this->travel(1)->minutes();
    $page->call('refreshGenerationRun')->assertSet('generationStatus', $status)
        ->assertActionDisabled('planGeneration');
    $this->travel(20)->minutes();

    $page = $entryPoint === 'mount' ? Livewire::test(DemoKitPage::class) : $page->call('refreshGenerationRun');
    $page->assertSet('generationStatus', DemoKitGenerationRun::STATUS_STALLED)
        ->assertActionEnabled('planGeneration')
        ->assertSee(__('capell-demo-kit::actions.example_site_data_stalled'));

    expect($run->refresh()->status)->toBe(DemoKitGenerationRun::STATUS_STALLED)
        ->and($run->finished_at)->not->toBeNull()
        ->and(DemoKitGenerationRun::query()->count())->toBe(1);
    Queue::assertNothingPushed();
})->with([DemoKitGenerationRun::STATUS_QUEUED, DemoKitGenerationRun::STATUS_RUNNING])
    ->with(['mount', 'poll']);

it('replaces a completed run with a visible fresh review before queueing again', function (): void {
    Gate::before(fn (): bool => true);
    test()->actingAs(User::factory()->create());

    $panel = Panel::make()->id('admin')->path('admin')->default();
    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
    Filament::bootCurrentPanel();
    Filament::setServingStatus();

    $run = DemoKitGenerationRun::query()->create([
        'status' => DemoKitGenerationRun::STATUS_COMPLETED,
        'parameters' => [],
        'review' => ['counts' => ['pages' => 1]],
        'fingerprint' => str_repeat('a', 64),
        'created_content' => [],
    ]);

    Livewire::test(DemoKitPage::class)
        ->assertSet('generationRunId', $run->getKey())
        ->assertActionHidden('queueGeneration')
        ->callAction('planGeneration', data: ['profile' => 'recommended'])
        ->assertHasNoActionErrors()
        ->assertSet('generationRunId', null)
        ->assertSet('generationStatus', null)
        ->assertSee(__('capell-demo-kit::page.review_heading'))
        ->assertActionVisible('queueGeneration');
});

it('renders the review counts, collision warning, created links, and every durable status', function (): void {
    Gate::before(fn (): bool => true);
    $user = User::factory()->create();
    test()->actingAs($user);

    $panel = Panel::make()->id('admin')->path('admin')->default();
    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
    Filament::bootCurrentPanel();
    Filament::setServingStatus();

    $review = [
        'counts' => ['sites' => 1, 'languages' => 2, 'pages' => 3, 'media' => 4],
        'collisions' => [['name' => 'Collision Site', 'outcome' => 'blocked']],
    ];

    Livewire::test(DemoKitPage::class)
        ->set('generationReview', $review)
        ->set('generationReviewFingerprint', str_repeat('b', 64))
        ->assertSee(__('capell-demo-kit::page.review_heading'))
        ->assertSee('Collision Site')
        ->assertSee('3');

    foreach ([
        DemoKitGenerationRun::STATUS_QUEUED,
        DemoKitGenerationRun::STATUS_RUNNING,
        DemoKitGenerationRun::STATUS_FAILED,
        DemoKitGenerationRun::STATUS_STALLED,
    ] as $status) {
        Livewire::test(DemoKitPage::class)
            ->set('generationStatus', $status)
            ->set('generationReview', $review)
            ->assertSee(__('capell-demo-kit::page.generation_status.' . $status))
            ->assertDontSee(__('capell-demo-kit::page.review_description'));
    }

    Livewire::test(DemoKitPage::class)
        ->set('generationStatus', DemoKitGenerationRun::STATUS_COMPLETED)
        ->set('generationReview', [
            'created_content' => [['name' => 'Generated Site', 'url' => '/admin/sites/1/edit']],
        ])
        ->set('generationReviewFingerprint', str_repeat('c', 64))
        ->assertSee(__('capell-demo-kit::page.generation_status.completed'))
        ->assertSee('Generated Site')
        ->assertSee('/admin/sites/1/edit');
});
