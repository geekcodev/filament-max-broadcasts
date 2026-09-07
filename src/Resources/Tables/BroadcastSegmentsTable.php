<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;

class BroadcastSegmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.table.id'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.table.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('chat_count')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.table.recipients'))
                    ->getStateUsing(
                        static fn (BroadcastSegment $record): string => (string) $record->getChatCountAttribute(),
                    ),
                TextColumn::make('description')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.table.description'))
                    ->limit(60)
                    ->placeholder(__('filament-max-broadcasts::broadcasts.segment.table.no_description')),
                TextColumn::make('created_at')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.table.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
