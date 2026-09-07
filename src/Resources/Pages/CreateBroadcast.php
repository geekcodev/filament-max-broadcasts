<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Resources\Pages\CreateRecord;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastResource;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastService;
use Illuminate\Support\Carbon;

class CreateBroadcast extends CreateRecord
{
    protected static string $resource = BroadcastResource::class;

    /**
     * @param  array{text: string, type?: string, images?: list<string>, videos?: list<string>, files?: list<string>, scheduled_at?: string|null, recipient_chat_ids?: list<int>|null, segment_ids?: list<int>|null}  $data
     */
    protected function handleRecordCreation(array $data): Broadcast
    {
        $creator = auth()->user();
        $scheduledAt = $data['scheduled_at'] ?? null;
        $attachments = [];

        foreach (['images' => 'image', 'videos' => 'video', 'files' => 'file'] as $key => $uploadType) {
            foreach ((array) ($data[$key] ?? []) as $path) {
                $attachments[] = ['upload_type' => $uploadType, 'path' => $path];
            }
        }

        $segmentIds = array_map('intval', (array) ($data['segment_ids'] ?? []));
        $segments = $segmentIds === []
            ? []
            : array_values(BroadcastSegment::query()->whereIn('id', $segmentIds)->get()->all());

        $rawChatIds = $data['recipient_chat_ids'] ?? [];
        $chatIds = array_map('intval', $rawChatIds);

        return app(BroadcastService::class)->create(
            text: $data['text'],
            scheduledAt: $scheduledAt !== null && $scheduledAt !== ''
                ? Carbon::parse($scheduledAt)
                : null,
            creator: $creator,
            attachments: $attachments,
            type: ($data['type'] ?? '') ?: News::News->value,
            chatIds: $chatIds === [] ? null : $chatIds,
            segments: $segments,
        );
    }
}
