<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use GeekCo\FilamentMaxBroadcasts\Support\ChatSelectionField;

class BroadcastSegmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('filament-max-broadcasts::broadcasts.segment.form.name'))
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label(__('filament-max-broadcasts::broadcasts.segment.form.description'))
                ->rows(2),
            ChatSelectionField::make(
                'chat_ids',
                __('filament-max-broadcasts::broadcasts.segment.form.recipients'),
                helperText: __('filament-max-broadcasts::broadcasts.segment.form.recipients_helper'),
            ),
        ]);
    }
}
