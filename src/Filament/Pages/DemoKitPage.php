<?php

declare(strict_types=1);

namespace Capell\DemoKit\Filament\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Capell\Admin\Filament\Pages\ExtensionsPage;
use Capell\DemoKit\Actions\BuildDemoGenerationReviewAction;
use Capell\DemoKit\Actions\ListDemoKitProvenanceSitesAction;
use Capell\DemoKit\Actions\QueueDemoKitGenerationAction;
use Capell\DemoKit\Actions\ReclaimStalledDemoKitGenerationRunsAction;
use Capell\DemoKit\Actions\ResetDemoKitProvenanceAction;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Capell\DemoKit\Support\Extensions\ExampleSiteDataActionSchema;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Override;
use RuntimeException;

final class DemoKitPage extends Page
{
    use HasPageShield;

    public ?int $generationRunId = null;

    public ?string $generationStatus = null;

    public ?string $generationError = null;

    /** @var array<string, mixed>|null */
    public ?array $generationReview = null;

    public ?string $generationReviewFingerprint = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $slug = 'demo-kit';

    protected static ?int $navigationSort = 92;

    protected string $view = 'capell-demo-kit::filament.pages.demo-kit';

    #[Override]
    public static function getNavigationLabel(): string
    {
        return (string) __('capell-demo-kit::page.navigation_label');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return (string) __('capell-admin::navigation.group_system');
    }

    #[Override]
    public static function canAccess(): bool
    {
        return app()->environment(['local', 'testing'])
            && ExtensionsPage::canManageExtensions();
    }

    #[Override]
    public function getTitle(): string
    {
        return (string) __('capell-demo-kit::page.title');
    }

    #[Override]
    public function getSubheading(): string
    {
        return (string) __('capell-demo-kit::page.subheading');
    }

    public function mount(): void
    {
        if (! Schema::hasTable('capell_demo_kit_generation_runs')) {
            return;
        }

        $run = DemoKitGenerationRun::query()->latest('id')->first();

        if ($run instanceof DemoKitGenerationRun) {
            $this->generationRunId = $run->id;
            $this->refreshGenerationRun();
        }
    }

    public function refreshGenerationRun(): void
    {
        if ($this->generationRunId === null) {
            return;
        }

        ReclaimStalledDemoKitGenerationRunsAction::run($this->generationRunId);
        $run = DemoKitGenerationRun::query()->find($this->generationRunId);

        if ($run instanceof DemoKitGenerationRun) {
            $this->applyGenerationRun($run);
        }
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('planGeneration')
                ->label(__('capell-demo-kit::actions.plan_generation'))
                ->icon(Heroicon::OutlinedCircleStack)
                ->authorize(fn (): bool => self::canAccess())
                ->schema(fn (): array => resolve(ExampleSiteDataActionSchema::class)->schema())
                ->modalHeading(__('capell-demo-kit::actions.plan_generation_heading'))
                ->modalDescription(__('capell-demo-kit::actions.plan_generation_description'))
                ->disabled(fn (): bool => in_array($this->generationStatus, ['queued', 'running'], true))
                ->successNotificationTitle(__('capell-demo-kit::actions.review_ready'))
                ->action(function (Action $action, array $data): void {
                    $actor = auth()->user();
                    $review = BuildDemoGenerationReviewAction::run(
                        resolve(ExampleSiteDataActionSchema::class)->options($data),
                        $actor instanceof Model ? $actor : null,
                    );
                    $this->generationReview = $review->toArray();
                    $this->generationReviewFingerprint = $review->fingerprint;
                    $this->generationRunId = null;
                    $this->generationStatus = null;
                    $this->generationError = null;
                    $action->success();
                }),
            Action::make('queueGeneration')
                ->label(__('capell-demo-kit::actions.queue_generation'))
                ->icon(Heroicon::OutlinedPlay)
                ->color('success')
                ->visible(fn (): bool => $this->generationReview !== null && $this->generationStatus === null)
                ->authorize(fn (): bool => self::canAccess())
                ->requiresConfirmation()
                ->modalHeading(__('capell-demo-kit::actions.queue_generation_heading'))
                ->modalDescription(fn (): string => $this->queueDescription())
                ->action(function (): void {
                    $actor = auth()->user();
                    $run = QueueDemoKitGenerationAction::run(
                        $this->generationReview['options'] ?? [],
                        $actor instanceof Model ? $actor : null,
                        $this->generationReviewFingerprint,
                        true,
                    );
                    $this->generationReview = null;
                    $this->generationReviewFingerprint = null;
                    $this->applyGenerationRun($run);
                }),
            Action::make('resetDemoSites')
                ->label(__('capell-demo-kit::actions.reset_demo_sites'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => $this->provenanceSiteOptions() !== [])
                ->authorize(fn (): bool => self::canAccess())
                ->schema(fn (): array => [Select::make('site_names')
                    ->label(__('capell-demo-kit::actions.reset_demo_sites_field'))
                    ->options($this->provenanceSiteOptions())
                    ->multiple()
                    ->required()])
                ->requiresConfirmation()
                ->modalHeading(__('capell-demo-kit::actions.reset_demo_sites_heading'))
                ->modalDescription(__('capell-demo-kit::actions.reset_demo_sites_description'))
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    ResetDemoKitProvenanceAction::run(
                        array_values($data['site_names'] ?? []),
                        $actor instanceof Model ? $actor : null,
                    );
                }),
        ];
    }

    /** @return array<string, string> */
    private function provenanceSiteOptions(): array
    {
        $actor = auth()->user();
        if (! $actor instanceof Model) {
            return [];
        }

        return ListDemoKitProvenanceSitesAction::run($actor)
            ->pluck('name', 'name')
            ->map(static fn (mixed $name): string => is_string($name) ? $name : '')
            ->all();
    }

    private function queueDescription(): string
    {
        $counts = $this->generationReview['counts'] ?? [];
        $counts = is_array($counts) ? $counts : [];

        return (string) __('capell-demo-kit::actions.queue_generation_description', [
            'sites' => $this->integerValue($counts['sites'] ?? 0),
            'languages' => $this->integerValue($counts['languages'] ?? 0),
            'pages' => $this->integerValue($counts['pages'] ?? 0),
            'media' => $this->integerValue($counts['media'] ?? 0),
            'fingerprint' => $this->generationReviewFingerprint ?? '',
        ]);
    }

    private function integerValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function applyGenerationRun(DemoKitGenerationRun $run): void
    {
        $runId = $run->getKey();
        throw_unless(is_int($runId), RuntimeException::class, 'Demo Kit generation run must have an integer key.');
        $this->generationRunId = $runId;
        $this->generationStatus = $run->status;
        $this->generationError = $run->error_message;
        $this->generationReview = is_array($run->review)
            ? [...$run->review, 'created_content' => $run->created_content]
            : null;
        $this->generationReviewFingerprint = $run->fingerprint;
    }
}
