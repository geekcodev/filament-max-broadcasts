<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources;

use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcasts;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ViewBroadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\RelationManagers\BroadcastRecipientsRelationManager;
use GeekCo\FilamentMaxBroadcasts\Resources\Schemas\BroadcastForm;
use GeekCo\FilamentMaxBroadcasts\Resources\Tables\BroadcastsTable;

class BroadcastResource extends Resource
{
    public static function getModel(): string
    {
        /** @var class-string<Broadcast> */
        return config('filament-max-broadcasts.broadcast_model', Broadcast::class);
    }

    public static function getModelLabel(): string
    {
        /** @var string|null $label */
        $label = config('filament-max-broadcasts.ui.label');

        return $label ?? __('filament-max-broadcasts::broadcasts.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        /** @var string|null $label */
        $label = config('filament-max-broadcasts.ui.plural_label');

        return $label ?? __('filament-max-broadcasts::broadcasts.resource.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        /** @var string|null $label */
        $label = config('filament-max-broadcasts.ui.navigation_label');

        return $label ?? __('filament-max-broadcasts::broadcasts.resource.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        /** @var string|null $group */
        $group = config('filament-max-broadcasts.ui.navigation_group');

        return $group;
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        /** @var string|BackedEnum|Htmlable|null $icon */
        $icon = config('filament-max-broadcasts.ui.navigation_icon');

        return $icon ?? 'heroicon-o-megaphone';
    }

    public static function getNavigationSort(): ?int
    {
        /** @var int|null $sort */
        $sort = config('filament-max-broadcasts.ui.navigation_sort');

        return $sort;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        /** @var string|null $slug */
        $slug = config('filament-max-broadcasts.ui.slug');

        return $slug ?? 'broadcasts';
    }

    public static function form(Schema $schema): Schema
    {
        return BroadcastForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BroadcastsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BroadcastRecipientsRelationManager::class,
        ];
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

    public static function canView(Model $record): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.view', 'broadcasts.view');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBroadcasts::route('/'),
            'create' => CreateBroadcast::route('/create'),
            'view' => ViewBroadcast::route('/{record}'),
        ];
    }
}
