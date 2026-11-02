<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Actions\RequestConsentAction;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSegmentRecipients;

class BroadcastSegmentsTable
{
    public static function configure(Table $table): Table
    {
        $managePermission = config()->string('filament-max-broadcasts.permissions.manage', 'broadcasts.manage');

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
                    )
                    ->description(static function (BroadcastSegment $record): ?string {
                        $count = (new BroadcastSegmentRecipients())->nonDialogCount($record);

                        if ($count === 0) {
                            return null;
                        }

                        /** @var string $message */
                        $message = __('filament-max-broadcasts::broadcasts.segment.view.non_dialog_warning', ['count' => $count]);

                        return $message;
                    }),
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
                RequestConsentAction::make()->iconButton(),
                ViewAction::make()->iconButton(),
                EditAction::make()->authorize($managePermission)->iconButton(),
                DeleteAction::make()->authorize($managePermission)->iconButton(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
