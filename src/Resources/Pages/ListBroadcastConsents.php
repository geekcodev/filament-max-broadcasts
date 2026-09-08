<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastConsentResource;

class ListBroadcastConsents extends ListRecords
{
    protected static string $resource = BroadcastConsentResource::class;
}
