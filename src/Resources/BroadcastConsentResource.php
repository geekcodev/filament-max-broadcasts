<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources;

use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcastConsents;
use GeekCo\FilamentMaxBroadcasts\Resources\Tables\BroadcastConsentsTable;
use Illuminate\Database\Eloquent\Model;

class BroadcastConsentResource extends Resource
{
    public static function getModel(): string
    {
        /** @var class-string<BroadcastConsent> */
        return config('filament-max-broadcasts.consent.consent_model', BroadcastConsent::class);
    }

    public static function getModelLabel(): string
    {
        return __('filament-max-broadcasts::broadcasts.consent.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-max-broadcasts::broadcasts.consent.resource.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-max-broadcasts::broadcasts.consent.resource.navigation_label');
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
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'broadcast-consents';
    }

    public static function canAccess(): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.view', 'broadcasts.view');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.manage', 'broadcasts.manage');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return BroadcastConsentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBroadcastConsents::route('/'),
        ];
    }
}
