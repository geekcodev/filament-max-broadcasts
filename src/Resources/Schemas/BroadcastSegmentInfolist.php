<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Schemas;

use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSegmentRecipients;

class BroadcastSegmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
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
                        TextEntry::make('recipients_summary')
                            ->hiddenLabel()
                            ->state(
                                static fn (BroadcastSegment $record): string => self::recipientsSummary($record),
                            ),
                        Action::make('manageRecipients')
                            ->label(__('filament-max-broadcasts::broadcasts.segment.view.manage_recipients'))
                            ->icon('heroicon-o-users')
                            ->outlined()
                            ->url(static fn (BroadcastSegment $record): string => BroadcastSegmentResource::getUrl(
                                'recipients',
                                ['record' => $record],
                            )),
                    ]),
            ]);
    }

    private static function nonDialogCount(BroadcastSegment $segment): int
    {
        return (new BroadcastSegmentRecipients())->nonDialogCount($segment);
    }

    private static function recipientsSummary(BroadcastSegment $segment): string
    {
        $count = $segment->getChatCountAttribute();

        if ($count === 0) {
            return __('filament-max-broadcasts::broadcasts.segment.view.no_recipients');
        }

        return __('filament-max-broadcasts::broadcasts.segment.view.recipients_count', ['count' => $count]);
    }
}
