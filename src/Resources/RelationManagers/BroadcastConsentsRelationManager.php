<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Resources\Tables\BroadcastConsentsTable;
use Illuminate\Database\Eloquent\Model;

class BroadcastConsentsRelationManager extends RelationManager
{
    protected static string $relationship = 'consents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-max-broadcasts::broadcasts.consent.resource.plural_label');
    }

    public function table(Table $table): Table
    {
        return BroadcastConsentsTable::configureForSegment($table);
    }
}
