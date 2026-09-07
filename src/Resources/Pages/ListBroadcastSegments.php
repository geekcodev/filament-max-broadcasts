<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;

class ListBroadcastSegments extends ListRecords
{
    protected static string $resource = BroadcastSegmentResource::class;
}
