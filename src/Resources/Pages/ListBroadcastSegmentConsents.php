<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;
use GeekCo\FilamentMaxBroadcasts\Resources\Tables\BroadcastConsentsTable;

class ListBroadcastSegmentConsents extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = BroadcastSegmentResource::class;

    protected string $view = 'filament-max-broadcasts::pages.segment-consents';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return __('filament-max-broadcasts::broadcasts.segment.consents_page.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament-max-broadcasts::broadcasts.segment.consents_page.summary');
    }

    public static function canAccess(array $parameters = []): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.view', 'max_broadcasts.view');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public function table(Table $table): Table
    {
        return BroadcastConsentsTable::configureForSegment($table)
            ->query($this->segment()->consents()->getQuery());
    }

    private function segment(): BroadcastSegment
    {
        /** @var BroadcastSegment $segment */
        $segment = $this->getRecord();

        return $segment;
    }
}
