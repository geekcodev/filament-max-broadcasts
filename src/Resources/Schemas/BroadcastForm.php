<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastAttachment;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypes;

class BroadcastForm
{
    public static function configure(Schema $schema): Schema
    {
        $imageDisk = config()->string('filament-max-broadcasts.image.disk', 'public');
        $imageDirectory = config()->string('filament-max-broadcasts.image.directory', 'broadcasts');
        $maxKb = config()->integer('filament-max-broadcasts.image.max_kb', 51200);
        $mimeTypes = (array) config('filament-max-broadcasts.image.accepted_mime_types', []);
        $imageMimeTypes = array_values(array_filter(
            $mimeTypes,
            static fn (mixed $mime): bool => is_string($mime) && str_starts_with($mime, 'image/'),
        ));
        $videoMimeTypes = array_values(array_filter(
            $mimeTypes,
            static fn (mixed $mime): bool => is_string($mime) && str_starts_with($mime, 'video/'),
        ));

        return $schema
            ->components([
                Section::make(__('filament-max-broadcasts::broadcasts.form.message_section'))
                    ->description(__('filament-max-broadcasts::broadcasts.form.message_section_description'))
                    ->schema([
                        Select::make('type')
                            ->label(__('filament-max-broadcasts::broadcasts.form.type'))
                            ->options(BroadcastTypes::options())
                            ->default(News::News->value)
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
                        FileUpload::make('images')
                            ->label(__('filament-max-broadcasts::broadcasts.form.images'))
                            ->disk($imageDisk)
                            ->directory($imageDirectory)
                            ->image()
                            ->imageEditor()
                            ->multiple()
                            ->maxSize($maxKb)
                            ->columnSpanFull()
                            ->helperText(__('filament-max-broadcasts::broadcasts.form.images_helper')),
                        FileUpload::make('videos')
                            ->label(__('filament-max-broadcasts::broadcasts.form.videos'))
                            ->disk($imageDisk)
                            ->directory($imageDirectory)
                            ->multiple()
                            ->acceptedFileTypes($videoMimeTypes)
                            ->maxSize($maxKb)
                            ->columnSpanFull(),
                        FileUpload::make('files')
                            ->label(__('filament-max-broadcasts::broadcasts.form.files'))
                            ->disk($imageDisk)
                            ->directory($imageDirectory)
                            ->multiple()
                            ->maxSize($maxKb)
                            ->columnSpanFull(),
                        DateTimePicker::make('scheduled_at')
                            ->label(__('filament-max-broadcasts::broadcasts.form.scheduled_at'))
                            ->helperText(__('filament-max-broadcasts::broadcasts.form.scheduled_at_helper'))
                            ->seconds(false)
                            ->after('now')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('filament-max-broadcasts::broadcasts.form.attachments_section'))
                    ->visibleOn('view')
                    ->schema([
                        self::attachmentsList(),
                    ]),
                Section::make(__('filament-max-broadcasts::broadcasts.form.stats_section'))
                    ->visibleOn('view')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('type')
                            ->label(__('filament-max-broadcasts::broadcasts.form.stats_type'))
                            ->state(fn (Broadcast $record): string => BroadcastTypes::label($record->type)),
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

    private static function attachmentsList(): Component
    {
        return RepeatableEntry::make('attachments')
            ->label(__('filament-max-broadcasts::broadcasts.form.attachments'))
            ->hiddenLabel()
            ->schema([
                TextEntry::make('path')
                    ->label(__('filament-max-broadcasts::broadcasts.form.attachment_item'))
                    ->state(function (BroadcastAttachment $attachment): string {
                        return \sprintf('[%s] %s', $attachment->upload_type->value, $attachment->path);
                    }),
            ]);
    }
}
