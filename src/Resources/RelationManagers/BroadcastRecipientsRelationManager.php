<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BroadcastRecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-max-broadcasts::broadcasts.recipients.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('maxChat.maxUser'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-max-broadcasts::broadcasts.recipients.name'))
                    ->getStateUsing(static function (BroadcastRecipient $record): string {
                        $user = $record->maxChat?->maxUser;
                        $name = trim(implode(' ', array_filter([$user?->first_name, $user?->last_name])));

                        return $name !== '' ? $name : __('filament-max-broadcasts::broadcasts.recipients.anonymous', ['id' => $record->user_id]);
                    })
                    ->searchable(query: static function (Builder $query, string $search): void {
                        $query->whereHas(
                            'maxChat.maxUser',
                            static fn (Builder $q) => $q->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%'),
                        );
                    }),
                TextColumn::make('user_id')
                    ->label(__('filament-max-broadcasts::broadcasts.recipients.user_id'))
                    ->searchable(),
                TextColumn::make('chat_id')
                    ->label(__('filament-max-broadcasts::broadcasts.recipients.chat_id'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('filament-max-broadcasts::broadcasts.recipients.status'))
                    ->badge()
                    ->color(fn (BroadcastRecipientStatus $state): string => match ($state) {
                        BroadcastRecipientStatus::Pending => 'gray',
                        BroadcastRecipientStatus::Sent => 'success',
                        BroadcastRecipientStatus::Failed => 'danger',
                    })
                    ->formatStateUsing(fn (BroadcastRecipientStatus $state): string => $state->label()),
                TextColumn::make('error')
                    ->label(__('filament-max-broadcasts::broadcasts.recipients.error'))
                    ->limit(40),
                TextColumn::make('sent_at')
                    ->label(__('filament-max-broadcasts::broadcasts.recipients.sent_at'))
                    ->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BroadcastRecipientStatus::labels())
                    ->label(__('filament-max-broadcasts::broadcasts.recipients.filter_status')),
            ])
            ->defaultSort('id');
    }
}
