<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastType;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;

class BroadcastForm
{
    public static function configure(Schema $schema): Schema
    {
        $imageDisk = config()->string('filament-max-broadcasts.image.disk', 'public');
        $imageDirectory = config()->string('filament-max-broadcasts.image.directory', 'broadcasts');
        $imageMaxKb = config()->integer('filament-max-broadcasts.image.max_kb', 10240);

        return $schema
            ->components([
                Section::make(__('filament-max-broadcasts::broadcasts.form.message_section'))
                    ->description(__('filament-max-broadcasts::broadcasts.form.message_section_description'))
                    ->schema([
                        Select::make('type')
                            ->label(__('filament-max-broadcasts::broadcasts.form.type'))
                            ->options(BroadcastType::labels())
                            ->default(BroadcastType::News->value)
                            ->required()
                            ->columnSpanFull()
                            ->helperText(__('filament-max-broadcasts::broadcasts.form.type_helper')),
                        RichEditor::make('text')
                            ->label(__('filament-max-broadcasts::broadcasts.form.text'))
                            ->required()
                            ->maxLength(4000)
                            ->toolbarButtons([
                                'blockquote',
                                'bold',
                                'codeBlock',
                                'h1',
                                'h2',
                                'h3',
                                'highlight',
                                'italic',
                                'link',
                                'strike',
                                'underline',
                            ])
                            ->columnSpanFull()
                            ->helperText(__('filament-max-broadcasts::broadcasts.form.text_helper')),
                        FileUpload::make('image_path')
                            ->label(__('filament-max-broadcasts::broadcasts.form.image'))
                            ->disk($imageDisk)
                            ->directory($imageDirectory)
                            ->image()
                            ->imageEditor()
                            ->maxSize($imageMaxKb)
                            ->nullable()
                            ->columnSpanFull()
                            ->visibleOn(['create', 'edit']),
                        DateTimePicker::make('scheduled_at')
                            ->label(__('filament-max-broadcasts::broadcasts.form.scheduled_at'))
                            ->helperText(__('filament-max-broadcasts::broadcasts.form.scheduled_at_helper'))
                            ->seconds(false)
                            ->after('now')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('filament-max-broadcasts::broadcasts.form.image_section'))
                    ->visibleOn('view')
                    ->schema([
                        ImageEntry::make('image_path')
                            ->label(__('filament-max-broadcasts::broadcasts.form.attached_image'))
                            ->disk($imageDisk)
                            ->imageSize(200)
                            ->visible(fn (Broadcast $record): bool => $record->image_path !== null),
                    ]),
                Section::make(__('filament-max-broadcasts::broadcasts.form.stats_section'))
                    ->visibleOn('view')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('type')
                            ->label(__('filament-max-broadcasts::broadcasts.form.stats_type'))
                            ->state(fn (Broadcast $record): string => $record->type->label()),
                        TextEntry::make('status')
                            ->label(__('filament-max-broadcasts::broadcasts.form.stats_status'))
                            ->state(fn (Broadcast $record): string => $record->status->label()),
                        TextEntry::make('sent_at')
                            ->label(__('filament-max-broadcasts::broadcasts.form.stats_sent_at'))
                            ->state(fn (Broadcast $record): string => $record->sent_at?->format('d.m.Y H:i') ?? '—'),
                        TextEntry::make('delivered')
                            ->label(__('filament-max-broadcasts::broadcasts.form.stats_delivered'))
                            ->state(fn (Broadcast $record): string => (string) $record->delivered_count),
                        TextEntry::make('failed')
                            ->label(__('filament-max-broadcasts::broadcasts.form.stats_failed'))
                            ->state(fn (Broadcast $record): string => (string) $record->failed_count),
                    ]),
            ]);
    }
}
