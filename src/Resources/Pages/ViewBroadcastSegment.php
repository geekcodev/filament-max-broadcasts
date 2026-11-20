<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use GeekCo\FilamentMaxBroadcasts\Resources\Actions\RequestConsentAction;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;

class ViewBroadcastSegment extends ViewRecord
{
    protected static string $resource = BroadcastSegmentResource::class;

    protected function getHeaderActions(): array
    {
        $managePermission = config()->string('filament-max-broadcasts.permissions.manage', 'max_broadcasts.manage');

        return [
            RequestConsentAction::make()->record($this->getRecord()),
            EditAction::make()->authorize($managePermission)->outlined()->icon('heroicon-o-pencil-square'),
            DeleteAction::make()->authorize($managePermission)->outlined()->icon('heroicon-o-trash'),
        ];
    }
}
