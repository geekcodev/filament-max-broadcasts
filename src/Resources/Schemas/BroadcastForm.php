<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastAttachment;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypes;
use GeekCo\MaxPhpClient\Enum\UploadType;
use Illuminate\Support\Facades\Storage;

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
                            ->hiddenOn('view')
                            ->helperText(__('filament-max-broadcasts::broadcasts.form.images_helper')),
                        FileUpload::make('videos')
                            ->label(__('filament-max-broadcasts::broadcasts.form.videos'))
                            ->disk($imageDisk)
                            ->directory($imageDirectory)
                            ->multiple()
                            ->acceptedFileTypes($videoMimeTypes)
                            ->maxSize($maxKb)
                            ->columnSpanFull()
                            ->hiddenOn('view'),
                        FileUpload::make('files')
                            ->label(__('filament-max-broadcasts::broadcasts.form.files'))
                            ->disk($imageDisk)
                            ->directory($imageDirectory)
                            ->multiple()
                            ->maxSize($maxKb)
                            ->columnSpanFull()
                            ->hiddenOn('view'),
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
        $imageDisk = config()->string('filament-max-broadcasts.image.disk', 'public');

        return RepeatableEntry::make('attachments')
            ->label(__('filament-max-broadcasts::broadcasts.form.attachments'))
            ->hiddenLabel()
            ->schema([
                ImageEntry::make('path')
                    ->label(__('filament-max-broadcasts::broadcasts.form.attachment_item'))
                    ->disk($imageDisk)
                    ->height(120)
                    ->square()
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->state(
                        static fn (BroadcastAttachment $attachment): ?string => self::attachmentPath($attachment),
                    )
                    ->visible(
                        static fn (BroadcastAttachment $attachment): bool => self::isImagePreviewable($attachment),
                    ),
                TextEntry::make('path')
                    ->label(__('filament-max-broadcasts::broadcasts.form.attachment_item'))
                    ->html()
                    ->state(
                        static function (BroadcastAttachment $attachment): string {
                            $url = self::attachmentFileUrl($attachment);
                            $name = \sprintf('%s — %s', self::attachmentTypeLabel($attachment), basename($attachment->path));

                            if ($url === null) {
                                return \e($name);
                            }

                            return \sprintf(
                                '<a href="%s" target="_blank" rel="noopener nofollow">%s</a>',
                                \e($url),
                                \e($name),
                            );
                        },
                    )
                    ->visible(
                        static fn (BroadcastAttachment $attachment): bool => ! self::isImagePreviewable($attachment),
                    ),
            ]);
    }

    private static function isImagePreviewable(BroadcastAttachment $attachment): bool
    {
        return $attachment->upload_type === UploadType::Image && self::attachmentPath($attachment) !== null;
    }

    private static function attachmentTypeLabel(BroadcastAttachment $attachment): string
    {
        return __("filament-max-broadcasts::broadcasts.form.attachment_types.{$attachment->upload_type->value}");
    }

    private static function attachmentPath(BroadcastAttachment $attachment): ?string
    {
        $path = trim($attachment->path);

        return $path !== '' ? $path : null;
    }

    private static function attachmentFileUrl(BroadcastAttachment $attachment): ?string
    {
        $path = self::attachmentPath($attachment);

        if ($path === null) {
            return null;
        }

        $disk = config()->string('filament-max-broadcasts.image.disk', 'public');
        $url = Storage::disk($disk)->url($path);

        return $url !== '' ? $url : null;
    }
}
