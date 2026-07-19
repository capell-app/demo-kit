<?php

declare(strict_types=1);

namespace Capell\DemoKit\Filament\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Capell\Admin\Filament\Pages\ExtensionsPage;
use Capell\DemoKit\Actions\QueueDemoKitGenerationAction;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Capell\DemoKit\Support\Extensions\ExampleSiteDataActionSchema;
use Filament\Actions\Action;
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
            $this->applyGenerationRun($run);
        }
    }

    public function refreshGenerationRun(): void
    {
        if ($this->generationRunId === null) {
            return;
        }

        $run = DemoKitGenerationRun::query()->find($this->generationRunId);

        if ($run instanceof DemoKitGenerationRun) {
            $this->applyGenerationRun($run);
        }
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('insertExampleSiteData')
                ->label(__('capell-demo-kit::actions.insert_example_site_data'))
                ->icon(Heroicon::OutlinedCircleStack)
                ->authorize(fn (): bool => self::canAccess())
                ->schema(fn (): array => resolve(ExampleSiteDataActionSchema::class)->schema())
                ->modalHeading(__('capell-demo-kit::actions.insert_example_site_data_heading'))
                ->modalDescription(__('capell-demo-kit::actions.insert_example_site_data_description'))
                ->disabled(fn (): bool => in_array($this->generationStatus, ['queued', 'running'], true))
                ->successNotificationTitle(__('capell-demo-kit::actions.example_site_data_queued'))
                ->action(function (Action $action, array $data): void {
                    $actor = auth()->user();
                    $run = QueueDemoKitGenerationAction::run($data, $actor instanceof Model ? $actor : null);
                    $this->applyGenerationRun($run);
                    $action->success();
                }),
        ];
    }

    private function applyGenerationRun(DemoKitGenerationRun $run): void
    {
        $runId = $run->getKey();
        throw_unless(is_int($runId), RuntimeException::class, 'Demo Kit generation run must have an integer key.');
        $this->generationRunId = $runId;
        $this->generationStatus = $run->status;
        $this->generationError = $run->error_message;
    }
}
