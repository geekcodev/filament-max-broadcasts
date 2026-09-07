<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastResource;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentRequestService;

class ListBroadcasts extends ListRecords
{
    protected static string $resource = BroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('request_consent')
                ->label(__('filament-max-broadcasts::broadcasts.actions.request_consent'))
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading(__('filament-max-broadcasts::broadcasts.actions.request_consent_heading'))
                ->modalDescription(__('filament-max-broadcasts::broadcasts.actions.request_consent_description'))
                ->modalSubmitActionLabel(__('filament-max-broadcasts::broadcasts.actions.request_consent_submit'))
                ->authorize(static fn (): bool => auth()->user()?->can(
                    config()->string('filament-max-broadcasts.permissions.create', 'broadcasts.create'),
                ) ?? false)
                ->action(function (): void {
                    $count = app(ConsentRequestService::class)->sendRequest();

                    if ($count === 0) {
                        Notification::make()
                            ->title(__('filament-max-broadcasts::broadcasts.notifications.consent_no_recipients'))
                            ->info()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('filament-max-broadcasts::broadcasts.notifications.consent_request_started', ['count' => $count]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
