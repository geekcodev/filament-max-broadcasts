<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;

class BroadcastConsentsTable
{
    public static function configure(Table $table): Table
    {
        $managePermission = config()->string('filament-max-broadcasts.permissions.manage', 'broadcasts.manage');

        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('filament-max-broadcasts::broadcasts.consent_table.id'))
                    ->sortable(),
                TextColumn::make('chat_id')
                    ->label(__('filament-max-broadcasts::broadcasts.consent_table.chat_id'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('segment.name')
                    ->label(__('filament-max-broadcasts::broadcasts.consent_table.segment'))
                    ->sortable(),
                TextColumn::make('action')
                    ->label(__('filament-max-broadcasts::broadcasts.consent_table.action'))
                    ->badge()
                    ->color(fn (BroadcastConsentAction $state): string => match ($state) {
                        BroadcastConsentAction::OptIn => 'success',
                        BroadcastConsentAction::OptOut => 'danger',
                    })
                    ->formatStateUsing(fn (BroadcastConsentAction $state): string => $state->label()),
                TextColumn::make('source')
                    ->label(__('filament-max-broadcasts::broadcasts.consent_table.source')),
                TextColumn::make('created_at')
                    ->label(__('filament-max-broadcasts::broadcasts.consent_table.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(BroadcastConsentAction::labels())
                    ->label(__('filament-max-broadcasts::broadcasts.consent_table.filter_action')),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->authorize($managePermission)
                    ->iconButton(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
