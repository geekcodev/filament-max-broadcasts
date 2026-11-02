<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSegmentRecipients;
use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\FilamentMaxBroadcasts\Support\ChatSelectionField;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Database\Eloquent\Builder;

class BroadcastSegmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament-max-broadcasts::broadcasts.segment.view.info_section'))
                ->columns(2)
                ->schema([
                    TextEntry::make('name')
                        ->label(__('filament-max-broadcasts::broadcasts.segment.form.name')),
                    TextEntry::make('chat_count')
                        ->label(__('filament-max-broadcasts::broadcasts.segment.table.recipients'))
                        ->state(
                            static fn (BroadcastSegment $record): string => (string) $record->getChatCountAttribute(),
                        ),
                    TextEntry::make('description')
                        ->label(__('filament-max-broadcasts::broadcasts.segment.form.description'))
                        ->placeholder(__('filament-max-broadcasts::broadcasts.segment.table.no_description'))
                        ->columnSpanFull(),
                    TextEntry::make('created_at')
                        ->label(__('filament-max-broadcasts::broadcasts.segment.table.created_at'))
                        ->dateTime('d.m.Y H:i'),
                    TextEntry::make('non_dialogs')
                        ->hiddenLabel()
                        ->color('warning')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->columnSpanFull()
                        ->visible(static fn (BroadcastSegment $record): bool => self::nonDialogCount($record) > 0)
                        ->state(static fn (BroadcastSegment $record): string => __(
                            'filament-max-broadcasts::broadcasts.segment.view.non_dialog_warning',
                            ['count' => self::nonDialogCount($record)],
                        )),
                ]),
            Section::make(__('filament-max-broadcasts::broadcasts.segment.view.recipients_section'))
                ->description(__('filament-max-broadcasts::broadcasts.segment.view.recipients_section_description'))
                ->schema([
                    TextEntry::make('recipients')
                        ->hiddenLabel()
                        ->html()
                        ->placeholder(__('filament-max-broadcasts::broadcasts.segment.view.no_recipients'))
                        ->state(
                            static fn (BroadcastSegment $record): string => self::recipientsHtml($record),
                        ),
                ]),
        ]);
    }

    private static function nonDialogCount(BroadcastSegment $segment): int
    {
        return (new BroadcastSegmentRecipients())->nonDialogCount($segment);
    }

    /**
     * Получатели выводятся так же, как в селекте формы: бейдж типа, имя и ID.
     */
    private static function recipientsHtml(BroadcastSegment $segment): string
    {
        /** @var list<int> $chatIds */
        $chatIds = array_values(array_unique(array_map('intval', $segment->chat_ids ?? [])));

        if ($chatIds === []) {
            return '';
        }

        $model = ChatRegistry::model();

        /** @var Builder<MaxChat> $query */
        $query = ChatRegistry::withUsers($model::query()->whereIn('chat_id', $chatIds));

        /** @var array<int, MaxChat> $chats */
        $chats = [];

        foreach ($query->get() as $chat) {
            $chats[ChatRegistry::chatId($chat)] = $chat;
        }

        $lines = [];

        foreach ($chatIds as $chatId) {
            $chat = $chats[$chatId] ?? null;

            $lines[] = $chat !== null
                ? ChatSelectionField::labelFor($chat)
                : e(__('filament-max-broadcasts::broadcasts.segment.view.chat_fallback', ['id' => $chatId]));
        }

        return implode('<br>', $lines);
    }
}
