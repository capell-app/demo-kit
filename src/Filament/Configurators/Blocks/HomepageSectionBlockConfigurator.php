<?php

declare(strict_types=1);

namespace Capell\DemoKit\Filament\Configurators\Blocks;

use Capell\LayoutBuilder\Filament\Configurators\Blocks\DefaultBlockConfigurator;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;
use Override;

final class HomepageSectionBlockConfigurator extends DefaultBlockConfigurator
{
    #[Override]
    protected function detailsTab(): Tab
    {
        return Tab::make('homepage_content')
            ->label(__('capell-demo-kit::form.homepage_content'))
            ->icon('heroicon-o-home')
            ->schema([
                TextInput::make('meta.content.eyebrow')
                    ->label(__('capell-demo-kit::form.eyebrow')),
                Textarea::make('meta.content.heading')
                    ->label(__('capell-demo-kit::form.heading'))
                    ->rows(2),
                Textarea::make('meta.content.copy')
                    ->label(__('capell-demo-kit::form.copy'))
                    ->rows(3),
                TextInput::make('meta.content.image_alt')
                    ->label(__('capell-demo-kit::form.image_alt')),
                TextInput::make('meta.content.primary_label')
                    ->label(__('capell-demo-kit::form.primary_label')),
                TextInput::make('meta.content.primary_url')
                    ->label(__('capell-demo-kit::form.primary_url')),
                TextInput::make('meta.content.secondary_label')
                    ->label(__('capell-demo-kit::form.secondary_label')),
                TextInput::make('meta.content.secondary_url')
                    ->label(__('capell-demo-kit::form.secondary_url')),
                TextInput::make('meta.content.action_label')
                    ->label(__('capell-demo-kit::form.action_label')),
                TextInput::make('meta.content.action_url')
                    ->label(__('capell-demo-kit::form.action_url')),
                Repeater::make('meta.content.metrics')
                    ->label(__('capell-demo-kit::form.metrics'))
                    ->schema([
                        TextInput::make('value')
                            ->label(__('capell-demo-kit::form.value')),
                        TextInput::make('label')
                            ->label(__('capell-demo-kit::form.label')),
                    ])
                    ->defaultItems(0)
                    ->reorderable(),
                Repeater::make('meta.content.cards')
                    ->label(__('capell-demo-kit::form.cards'))
                    ->schema([
                        TextInput::make('eyebrow')
                            ->label(__('capell-demo-kit::form.eyebrow')),
                        TextInput::make('title')
                            ->label(__('capell-demo-kit::form.title')),
                        Textarea::make('copy')
                            ->label(__('capell-demo-kit::form.copy'))
                            ->rows(3),
                        Repeater::make('badges')
                            ->label(__('capell-demo-kit::form.badges'))
                            ->simple(TextInput::make('badge'))
                            ->defaultItems(0),
                        Repeater::make('steps')
                            ->label(__('capell-demo-kit::form.steps'))
                            ->schema([
                                TextInput::make('title')
                                    ->label(__('capell-demo-kit::form.title')),
                                TextInput::make('copy')
                                    ->label(__('capell-demo-kit::form.copy')),
                            ])
                            ->defaultItems(0),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible(),
                Repeater::make('meta.content.items')
                    ->label(__('capell-demo-kit::form.items'))
                    ->schema([
                        TextInput::make('code')
                            ->label(__('capell-demo-kit::form.code')),
                        TextInput::make('label')
                            ->label(__('capell-demo-kit::form.label')),
                        TextInput::make('eyebrow')
                            ->label(__('capell-demo-kit::form.eyebrow')),
                        TextInput::make('title')
                            ->label(__('capell-demo-kit::form.title')),
                        Textarea::make('description')
                            ->label(__('capell-demo-kit::form.description'))
                            ->rows(3),
                        TextInput::make('metric')
                            ->label(__('capell-demo-kit::form.metric')),
                        TextInput::make('cta')
                            ->label(__('capell-demo-kit::form.cta')),
                        TextInput::make('url')
                            ->label(__('capell-demo-kit::form.url')),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible(),
                Repeater::make('meta.content.steps')
                    ->label(__('capell-demo-kit::form.steps'))
                    ->schema([
                        TextInput::make('number')
                            ->label(__('capell-demo-kit::form.number')),
                        TextInput::make('title')
                            ->label(__('capell-demo-kit::form.title')),
                        Textarea::make('copy')
                            ->label(__('capell-demo-kit::form.copy'))
                            ->rows(2),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible(),
            ]);
    }
}
