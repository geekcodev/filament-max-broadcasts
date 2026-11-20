<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;

class CreateBroadcastSegment extends CreateRecord
{
    protected static string $resource = BroadcastSegmentResource::class;

    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl();
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->outlined()->icon('heroicon-o-plus');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->outlined()->icon('heroicon-o-x-mark');
    }

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
