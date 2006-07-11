<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Extensions;

use Capell\Admin\Filament\Components\Forms\LanguageSelect;
use Capell\Admin\Filament\Components\Forms\SiteSelect;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;

final class ExampleSiteDataActionSchema
{
    /**
     * @return array<int, mixed>
     */
    public function schema(): array
    {
        return [
            Radio::make('profile')
                ->label(__('capell-demo-kit::actions.profile'))
                ->options([
                    'recommended' => __('capell-demo-kit::actions.profile_recommended'),
                    'custom' => __('capell-demo-kit::actions.profile_custom'),
                ])
                ->default('recommended')
                ->live(),
            TextInput::make('url')
                ->label(__('capell-admin::form.url'))
                ->default(config('app.url'))
                ->visible(fn (callable $get): bool => $get('profile') === 'custom')
                ->required(fn (callable $get): bool => $get('profile') === 'custom')
                ->url(),
            LanguageSelect::make('languages')
                ->optionKey('code')
                ->multiple()
                ->withOptions()
                ->visible(fn (callable $get): bool => $get('profile') === 'custom'),
            SiteSelect::make('sites')
                ->optionKey('name')
                ->multiple()
                ->visible(fn (callable $get): bool => $get('profile') === 'custom'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function options(array $data): array
    {
        if (($data['profile'] ?? 'recommended') !== 'custom') {
            return [];
        }

        return collect($data)->except('profile')->all();
    }
}
