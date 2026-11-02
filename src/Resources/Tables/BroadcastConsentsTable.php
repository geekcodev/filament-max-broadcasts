<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\FilamentMaxBroadcasts\Support\ChatSelectionField;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BroadcastConsentsTable
{
    public static function configure(Table $table): Table
    {
        return self::apply($table, withSegment: true);
    }

    public static function configureForSegment(Table $table): Table
    {
        return self::apply($table, withSegment: false);
    }

    private static function apply(Table $table, bool $withSegment): Table
    {
        $managePermission = config()->string('filament-max-broadcasts.permissions.manage', 'broadcasts.manage');

        $columns = [
            TextColumn::make('id')
                ->label(__('filament-max-broadcasts::broadcasts.consent_table.id'))
                ->sortable(),
            TextColumn::make('chat_id')
                ->label(__('filament-max-broadcasts::broadcasts.consent_table.chat_id'))
                ->html()
                ->getStateUsing(static fn (BroadcastConsent $record): string => self::chatBadges($record))
                ->sortable()
                ->searchable(),
            TextColumn::make('user_id')
                ->label(__('filament-max-broadcasts::broadcasts.consent_table.user_id'))
                ->html()
                ->getStateUsing(
                    static fn (BroadcastConsent $record): ?string => $record->user_id !== null
                        ? ChatSelectionField::idBadge($record->user_id)
                        : null,
                )
                ->placeholder('—')
                ->sortable(),
            TextColumn::make('name')
                ->label(__('filament-max-broadcasts::broadcasts.consent_table.name'))
                ->getStateUsing(
                    static fn (BroadcastConsent $record): string => $record->chat instanceof Model
                        ? ChatSelectionField::displayName($record->chat)
                        : __('filament-max-broadcasts::broadcasts.consent_table.anonymous_chat', ['id' => $record->chat_id]),
                )
                ->searchable(query: static function (Builder $query, string $search): void {
                    $query->orWhereHas('chat', static function (Builder $chatQuery) use ($search): void {
                        $chatQuery->where(static function (Builder $userQuery) use ($search): void {
                            /** @var Builder<MaxChat> $userQuery */
                            ChatRegistry::whereUserLike($userQuery, $search);
                        });
                    });
                }),
        ];

        if ($withSegment) {
            $columns[] = TextColumn::make('segment.name')
                ->label(__('filament-max-broadcasts::broadcasts.consent_table.segment'))
                ->sortable();
        }

        return $table
            ->modifyQueryUsing(
                static fn (Builder $query): Builder => $query->with([
                    'segment',
                    ...array_map(
                        static fn (string $relation): string => 'chat.'.$relation,
                        ChatRegistry::userRelations(),
                    ),
                ]),
            )
            ->columns([
                ...$columns,
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
                ...($withSegment ? [self::segmentFilter()] : []),
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

    /**
     * Фильтр общего журнала по сегменту; записи удалённых сегментов (segment_id = null)
     * доступны вариантом «Без сегмента».
     */
    private static function segmentFilter(): SelectFilter
    {
        return SelectFilter::make('segment_id')
            ->label(__('filament-max-broadcasts::broadcasts.consent_table.filter_segment'))
            ->options(static function (): array {
                $options = [];

                foreach (BroadcastSegment::query()->orderBy('name')->get() as $segment) {
                    /** @var int $key */
                    $key = $segment->getKey();
                    $options[$key] = (string) $segment->name;
                }

                $options['none'] = __('filament-max-broadcasts::broadcasts.consent_table.no_segment');

                return $options;
            })
            ->query(static function (Builder $query, array $data): Builder {
                $value = $data['value'] ?? null;

                if ($value === null || $value === '') {
                    return $query;
                }

                if ($value === 'none') {
                    return $query->whereNull('segment_id');
                }

                if (! is_int($value) && ! is_string($value)) {
                    return $query;
                }

                return $query->where('segment_id', (int) $value);
            });
    }

    private static function chatBadges(BroadcastConsent $record): string
    {
        $chat = $record->chat;
        $type = $chat instanceof Model ? ChatSelectionField::typeBadge($chat).' ' : '';

        return $type.ChatSelectionField::idBadge($record->chat_id);
    }
}
