<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Resources\Pages\CreateRecord;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;

class CreateBroadcastSegment extends CreateRecord
{
    protected static string $resource = BroadcastSegmentResource::class;

    /**
     * @param  array{name: string, description?: string|null, chat_ids: list<int>}  $data
     */
    protected function handleRecordCreation(array $data): BroadcastSegment
    {
        return BroadcastSegment::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'chat_ids' => array_map('intval', $data['chat_ids']),
            'created_by' => auth()->id(),
        ]);
    }
}
