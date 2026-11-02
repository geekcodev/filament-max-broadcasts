<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;

class ListBroadcastSegments extends ListRecords
{
    protected static string $resource = BroadcastSegmentResource::class;

    protected function getHeaderActions(): array
    {
        $createPermission = config()->string('filament-max-broadcasts.permissions.create', 'broadcasts.create');

        return [
            CreateAction::make()->authorize($createPermission),
        ];
    }
}
