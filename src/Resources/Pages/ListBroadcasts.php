<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastResource;

class ListBroadcasts extends ListRecords
{
    protected static string $resource = BroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
