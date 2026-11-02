<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentRequestService;

/**
 * Действие «Запросить согласие» для сегмента: страница сегмента и строка таблицы.
 */
class RequestConsentAction
{
    public static function make(): Action
    {
        $createPermission = config()->string('filament-max-broadcasts.permissions.create', 'broadcasts.create');

        return Action::make('request_consent')
            ->label(__('filament-max-broadcasts::broadcasts.actions.request_consent'))
            ->icon('heroicon-o-check-badge')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading(__('filament-max-broadcasts::broadcasts.actions.request_consent_heading'))
            ->modalDescription(__('filament-max-broadcasts::broadcasts.actions.request_consent_description'))
            ->modalSubmitActionLabel(__('filament-max-broadcasts::broadcasts.actions.request_consent_submit'))
            ->authorize(static fn (): bool => auth()->user()?->can($createPermission) ?? false)
            ->action(function (BroadcastSegment $record): void {
                $count = app(ConsentRequestService::class)->sendRequest($record);

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
            });
    }
}
