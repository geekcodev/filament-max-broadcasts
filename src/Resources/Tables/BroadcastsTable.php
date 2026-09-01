<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastType;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastService;

class BroadcastsTable
{
    public static function configure(Table $table): Table
    {
        $managePermission = config()->string('filament-max-broadcasts.permissions.manage', 'broadcasts.manage');

        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('filament-max-broadcasts::broadcasts.table.id'))
                    ->sortable(),
                TextColumn::make('text')
                    ->label(__('filament-max-broadcasts::broadcasts.table.text'))
                    ->getStateUsing(static fn (Broadcast $record): string => str(strip_tags($record->text))->squish()->limit(50)->toString())
                    ->searchable(),
                TextColumn::make('has_image')
                    ->label(__('filament-max-broadcasts::broadcasts.table.image'))
                    ->getStateUsing(
                        static fn (Broadcast $record): string => $record->image_path !== null
                            ? __('filament-max-broadcasts::broadcasts.table.has_image')
                            : __('filament-max-broadcasts::broadcasts.table.no_image'),
                    ),
                TextColumn::make('type')
                    ->label(__('filament-max-broadcasts::broadcasts.table.type'))
                    ->badge()
                    ->color(fn (BroadcastType $state): string => match ($state) {
                        BroadcastType::News => 'gray',
                        BroadcastType::Promo => 'success',
                    })
                    ->formatStateUsing(fn (BroadcastType $state): string => $state->label()),
                TextColumn::make('status')
                    ->label(__('filament-max-broadcasts::broadcasts.table.status'))
                    ->badge()
                    ->color(fn (BroadcastStatus $state): string => match ($state) {
                        BroadcastStatus::Scheduled => 'gray',
                        BroadcastStatus::Running => 'warning',
                        BroadcastStatus::Completed => 'success',
                        BroadcastStatus::Cancelled => 'danger',
                        BroadcastStatus::Failed => 'danger',
                    })
                    ->formatStateUsing(fn (BroadcastStatus $state): string => $state->label()),
                TextColumn::make('total_recipients')
                    ->label(__('filament-max-broadcasts::broadcasts.table.total_recipients'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('delivered_count')
                    ->label(__('filament-max-broadcasts::broadcasts.table.delivered_count'))
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BroadcastStatus::labels())
                    ->label(__('filament-max-broadcasts::broadcasts.table.filter_status')),
                SelectFilter::make('type')
                    ->options(BroadcastType::labels())
                    ->label(__('filament-max-broadcasts::broadcasts.table.filter_type')),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                Action::make('repeat')
                    ->label(__('filament-max-broadcasts::broadcasts.actions.repeat'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading(__('filament-max-broadcasts::broadcasts.actions.repeat_heading'))
                    ->modalDescription(__('filament-max-broadcasts::broadcasts.actions.repeat_description'))
                    ->modalSubmitActionLabel(__('filament-max-broadcasts::broadcasts.actions.repeat_submit'))
                    ->authorize($managePermission)
                    ->action(function (Broadcast $record): void {
                        $user = auth()->user();

                        app(BroadcastService::class)->create(
                            text: $record->text,
                            scheduledAt: null,
                            creator: $user,
                            imagePath: $record->image_path,
                            type: $record->type,
                        );

                        Notification::make()
                            ->title(__('filament-max-broadcasts::broadcasts.notifications.broadcast_started'))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->authorize($managePermission)
                    ->iconButton(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
