<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;

class EditBroadcastSegment extends EditRecord
{
    protected static string $resource = BroadcastSegmentResource::class;

    protected function getRedirectUrl(): ?string
    {
        return $this->getResourceUrl();
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->outlined()->icon('heroicon-o-check');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->outlined()->icon('heroicon-o-x-mark');
    }

    /**
     * @param  BroadcastSegment  $record
     * @param  array{name: string, description?: string|null, chat_ids: list<int>}  $data
     */
    protected function handleRecordUpdate($record, array $data): BroadcastSegment
    {
        $record->forceFill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'chat_ids' => array_map('intval', $data['chat_ids']),
        ])->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->outlined()->icon('heroicon-o-trash'),
        ];
    }
}
