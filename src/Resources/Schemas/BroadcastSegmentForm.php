<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BroadcastSegmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament-max-broadcasts::broadcasts.segment.form.main_section'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('filament-max-broadcasts::broadcasts.segment.form.name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label(__('filament-max-broadcasts::broadcasts.segment.form.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
