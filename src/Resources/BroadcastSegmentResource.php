<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources;

use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\EditBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcastSegmentConsents;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcastSegments;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ManageBroadcastSegmentRecipients;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ViewBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Schemas\BroadcastSegmentForm;
use GeekCo\FilamentMaxBroadcasts\Resources\Schemas\BroadcastSegmentInfolist;
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

    public static function infolist(Schema $schema): Schema
    {
        return BroadcastSegmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BroadcastSegmentsTable::configure($table);
    }

    public static function canAccess(): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.view', 'max_broadcasts.view');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function canCreate(): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.create', 'max_broadcasts.create');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function canView(Model $record): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.view', 'max_broadcasts.view');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function canEdit(Model $record): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.manage', 'max_broadcasts.manage');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function canDelete(Model $record): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.manage', 'max_broadcasts.manage');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBroadcastSegments::route('/'),
            'create' => CreateBroadcastSegment::route('/create'),
            'view' => ViewBroadcastSegment::route('/{record}'),
            'edit' => EditBroadcastSegment::route('/{record}/edit'),
            'recipients' => ManageBroadcastSegmentRecipients::route('/{record}/recipients'),
            'consents' => ListBroadcastSegmentConsents::route('/{record}/consents'),
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        $record = method_exists($page, 'getRecord') ? $page->getRecord() : null;

        if ($record === null) {
            return [];
        }

        return [
            NavigationItem::make(__('filament-max-broadcasts::broadcasts.segment.tabs.view'))
                ->icon('heroicon-o-eye')
                ->url(static::getUrl('view', ['record' => $record]))
                ->isActiveWhen(static fn (): bool => $page instanceof ViewBroadcastSegment),
            NavigationItem::make(__('filament-max-broadcasts::broadcasts.segment.tabs.main'))
                ->icon('heroicon-o-pencil-square')
                ->url(static::getUrl('edit', ['record' => $record]))
                ->isActiveWhen(static fn (): bool => $page instanceof EditBroadcastSegment),
            NavigationItem::make(__('filament-max-broadcasts::broadcasts.segment.tabs.recipients'))
                ->icon('heroicon-o-users')
                ->url(static::getUrl('recipients', ['record' => $record]))
                ->isActiveWhen(static fn (): bool => $page instanceof ManageBroadcastSegmentRecipients),
            NavigationItem::make(__('filament-max-broadcasts::broadcasts.segment.tabs.consents'))
                ->icon('heroicon-o-check-badge')
                ->url(static::getUrl('consents', ['record' => $record]))
                ->isActiveWhen(static fn (): bool => $page instanceof ListBroadcastSegmentConsents),
        ];
    }
}
