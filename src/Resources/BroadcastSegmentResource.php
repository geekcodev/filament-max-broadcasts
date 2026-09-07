<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources;

use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\EditBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcastSegments;
use GeekCo\FilamentMaxBroadcasts\Resources\Schemas\BroadcastSegmentForm;
use GeekCo\FilamentMaxBroadcasts\Resources\Tables\BroadcastSegmentsTable;
use Illuminate\Database\Eloquent\Model;

class BroadcastSegmentResource extends Resource
{
    public static function getModel(): string
    {
        /** @var class-string<BroadcastSegment> */
        return config('filament-max-broadcasts.segment_model', BroadcastSegment::class);
    }

    public static function getModelLabel(): string
    {
        return __('filament-max-broadcasts::broadcasts.segment.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-max-broadcasts::broadcasts.segment.resource.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-max-broadcasts::broadcasts.segment.resource.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        /** @var string|null $group */
        $group = config('filament-max-broadcasts.ui.navigation_group');

        return $group
            ?? __('filament-max-broadcasts::broadcasts.resource.navigation_group');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'broadcast-segments';
    }

    public static function form(Schema $schema): Schema
    {
        return BroadcastSegmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BroadcastSegmentsTable::configure($table);
    }

    public static function canAccess(): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.view', 'broadcasts.view');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function canCreate(): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.create', 'broadcasts.create');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function canEdit(Model $record): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.manage', 'broadcasts.manage');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function canDelete(Model $record): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.manage', 'broadcasts.manage');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBroadcastSegments::route('/'),
            'create' => CreateBroadcastSegment::route('/create'),
            'edit' => EditBroadcastSegment::route('/{record}/edit'),
        ];
    }
}
