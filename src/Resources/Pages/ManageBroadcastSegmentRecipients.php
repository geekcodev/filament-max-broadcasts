<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;
use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\FilamentMaxBroadcasts\Support\ChatSelectionField;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Database\Eloquent\Builder;

class ManageBroadcastSegmentRecipients extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = BroadcastSegmentResource::class;

    protected string $view = 'filament-max-broadcasts::pages.segment-recipients';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->selectedTableRecords = $this->segmentChatIds();
    }

    public function getTitle(): string
    {
        return __('filament-max-broadcasts::broadcasts.segment.recipients_page.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament-max-broadcasts::broadcasts.segment.recipients_page.summary', [
            'total' => count($this->allActiveDialogIds()),
        ]);
    }

    public static function canAccess(array $parameters = []): bool
    {
        $permission = config()->string('filament-max-broadcasts.permissions.view', 'max_broadcasts.view');

        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    public function table(Table $table): Table
    {
        $managePermission = config()->string('filament-max-broadcasts.permissions.manage', 'max_broadcasts.manage');

        return $table
            ->query($this->recipientsQuery())
            ->headerActions([
                Action::make('saveSelection')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.recipients_page.save'))
                    ->icon('heroicon-o-check')
                    ->outlined()
                    ->authorize($managePermission)
                    ->action(fn (): null => $this->saveSelection()),
            ])
            ->toolbarActions([
                Action::make('selectAll')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.recipients_page.select_all'))
                    ->icon('heroicon-o-check-circle')
                    ->color('gray')
                    ->outlined()
                    ->authorize($managePermission)
                    ->alpineClickHandler('selectAllRecords()'),
                Action::make('clearSelection')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.recipients_page.clear_selection'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->outlined()
                    ->authorize($managePermission)
                    ->alpineClickHandler('deselectAllRecords()'),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.recipients_page.name'))
                    ->state(static fn (MaxChat $record): string => ChatSelectionField::displayName($record))
                    ->searchable(query: static function (Builder $query, string $search): void {
                        $query->orWhere('chat_id', 'like', "%{$search}%")
                            ->orWhere(static function (Builder $userQuery) use ($search): void {
                                /** @var Builder<MaxChat> $userQuery */
                                ChatRegistry::whereUserLike($userQuery, $search);
                            });
                    }),
                TextColumn::make('chat_id')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.recipients_page.chat_id'))
                    ->sortable()
                    ->searchable(),
                IconColumn::make('consent_status')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.recipients_page.consent'))
                    ->getStateUsing(static function (MaxChat $record): ?BroadcastConsentAction {
                        $action = $record->getAttribute('consent_action');

                        return is_string($action) ? BroadcastConsentAction::tryFrom($action) : null;
                    })
                    ->icon(static fn (?BroadcastConsentAction $state): ?string => match ($state) {
                        BroadcastConsentAction::Pending => 'heroicon-o-clock',
                        BroadcastConsentAction::OptIn => 'heroicon-o-check-circle',
                        BroadcastConsentAction::OptOut => 'heroicon-o-x-circle',
                        null => null,
                    })
                    ->color(static fn (?BroadcastConsentAction $state): string => match ($state) {
                        BroadcastConsentAction::Pending => 'gray',
                        BroadcastConsentAction::OptIn => 'success',
                        BroadcastConsentAction::OptOut => 'danger',
                        null => 'gray',
                    })
                    ->tooltip(static fn (?BroadcastConsentAction $state): ?string => $state?->label()),
                TextColumn::make('last_activity_at')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.recipients_page.last_activity'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('selected')
                    ->label(__('filament-max-broadcasts::broadcasts.segment.recipients_page.only_selected'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $this->applySelectedFilter($query)),
            ])
            ->selectable(fn (): bool => $this->canManage($managePermission))
            ->checkIfRecordIsSelectableUsing(fn (): bool => $this->canManage($managePermission))
            ->defaultSort('last_activity_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading(__('filament-max-broadcasts::broadcasts.segment.recipients_page.empty_heading'))
            ->emptyStateDescription(__('filament-max-broadcasts::broadcasts.segment.recipients_page.empty_description'));
    }

    public function saveSelection(): void
    {
        $segment = $this->segment();

        $selectedQuery = $this->activeDialogsQuery();

        if ($this->isTrackingDeselectedTableRecords) {
            $selectedQuery->whereKeyNot($this->deselectedTableRecords);
        } else {
            $selectedQuery->whereKey($this->selectedTableRecords);
        }

        $selected = $selectedQuery
            ->pluck('chat_id')
            ->map(static fn (mixed $chatId): int => (int) (is_scalar($chatId) ? $chatId : 0))
            ->all();

        $allActive = $this->allActiveDialogIds();
        $preserved = array_values(array_diff($this->segmentChatIds(), $allActive));

        $chatIds = array_values(array_unique([...$preserved, ...$selected]));
        sort($chatIds);

        $segment->forceFill(['chat_ids' => $chatIds])->save();

        Notification::make()
            ->title(__('filament-max-broadcasts::broadcasts.segment.recipients_page.saved', ['count' => count($chatIds)]))
            ->success()
            ->send();
    }

    /**
     * @param  Builder<MaxChat>  $query
     * @return Builder<MaxChat>
     */
    private function applySelectedFilter(Builder $query): Builder
    {
        if ($this->isTrackingDeselectedTableRecords) {
            return $query->whereKeyNot($this->deselectedTableRecords);
        }

        return $query->whereKey($this->selectedTableRecords);
    }

    /**
     * @return Builder<MaxChat>
     */
    private function recipientsQuery(): Builder
    {
        $chatsModel = ChatRegistry::model();
        $chatsTable = (new $chatsModel())->getTable();

        /** @var class-string<BroadcastConsent> $consentModel */
        $consentModel = config('filament-max-broadcasts.consent.consent_model', BroadcastConsent::class);

        return $this->activeDialogsQuery()
            ->select($chatsTable.'.*')
            ->addSelect([
                'consent_action' => $consentModel::query()
                    ->select('action')
                    ->whereColumn('chat_id', $chatsTable.'.chat_id')
                    ->where('segment_id', $this->segment()->getKey())
                    ->limit(1),
            ]);
    }

    /**
     * @return Builder<MaxChat>
     */
    private function activeDialogsQuery(): Builder
    {
        $chatsModel = ChatRegistry::model();

        return ChatRegistry::withUsers(
            $chatsModel::query()
                ->where('status', MaxChatStatus::Active)
                ->where('chat_type', ChatType::Dialog->value),
        );
    }

    /**
     * @return list<int>
     */
    private function allActiveDialogIds(): array
    {
        $ids = [];

        foreach ($this->activeDialogsQuery()->pluck('chat_id') as $chatId) {
            if (is_scalar($chatId)) {
                $ids[] = (int) $chatId;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function segmentChatIds(): array
    {
        return $this->segment()->chat_ids ?? [];
    }

    private function segment(): BroadcastSegment
    {
        /** @var BroadcastSegment $segment */
        $segment = $this->getRecord();

        return $segment;
    }

    private function canManage(string $permission): bool
    {
        return auth()->user()?->can($permission) ?? false;
    }
}
