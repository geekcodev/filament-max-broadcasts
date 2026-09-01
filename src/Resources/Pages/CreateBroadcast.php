<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Resources\Pages\CreateRecord;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastType;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastResource;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastService;
use Illuminate\Support\Carbon;

class CreateBroadcast extends CreateRecord
{
    protected static string $resource = BroadcastResource::class;

    /**
     * @param  array{text: string, type?: string, image_path?: string|null, scheduled_at?: string|null}  $data
     */
    protected function handleRecordCreation(array $data): Broadcast
    {
        $creator = auth()->user();
        $scheduledAt = $data['scheduled_at'] ?? null;

        return app(BroadcastService::class)->create(
            text: $data['text'],
            scheduledAt: $scheduledAt !== null && $scheduledAt !== ''
                ? Carbon::parse($scheduledAt)
                : null,
            creator: $creator,
            imagePath: $data['image_path'] ?? null,
            type: isset($data['type']) && $data['type'] !== ''
                ? BroadcastType::tryFrom($data['type']) ?? BroadcastType::News
                : BroadcastType::News,
        );
    }
}
