<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Resources\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastResource;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastService;

class ViewBroadcast extends ViewRecord
{
    protected static string $resource = BroadcastResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Broadcast $broadcast */
        $broadcast = $this->getRecord();

        $managePermission = config()->string('filament-max-broadcasts.permissions.manage', 'broadcasts.manage');

        return [
            Action::make('repeat')
                ->label(__('filament-max-broadcasts::broadcasts.actions.repeat'))
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading(__('filament-max-broadcasts::broadcasts.actions.repeat_heading'))
                ->modalDescription(__('filament-max-broadcasts::broadcasts.actions.repeat_description'))
                ->modalSubmitActionLabel(__('filament-max-broadcasts::broadcasts.actions.repeat_submit'))
                ->authorize($managePermission)
                ->action(function (): void {
                    /** @var Broadcast $broadcast */
                    $broadcast = $this->getRecord();
                    $user = auth()->user();

                    app(BroadcastService::class)->create(
                        text: $broadcast->text,
                        scheduledAt: null,
                        creator: $user,
                        imagePath: $broadcast->image_path,
                        type: $broadcast->type,
                    );

                    Notification::make()
                        ->title(__('filament-max-broadcasts::broadcasts.notifications.broadcast_started'))
                        ->success()
                        ->send();
                }),
            Action::make('removeBroadcast')
                ->label(__('filament-max-broadcasts::broadcasts.actions.delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('filament-max-broadcasts::broadcasts.actions.delete'))
                ->modalSubmitActionLabel(__('filament-max-broadcasts::broadcasts.actions.delete'))
                ->authorize($managePermission)
                ->action(function (): void {
                    /** @var Broadcast $record */
                    $record = $this->getRecord();
                    $record->delete();
                }),
            Action::make('sendNow')
                ->label(__('filament-max-broadcasts::broadcasts.actions.send_now'))
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->visible(fn (): bool => $broadcast->status === BroadcastStatus::Scheduled)
                ->authorize($managePermission)
                ->action(function (): void {
                    $this->sendNow();
                }),
            Action::make('cancel')
                ->label(__('filament-max-broadcasts::broadcasts.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(
                    fn (): bool => in_array($broadcast->status, [
                        BroadcastStatus::Scheduled,
                        BroadcastStatus::Running,
                    ], true),
                )
                ->authorize($managePermission)
                ->action(function (): void {
                    $this->cancel();
                }),
        ];
    }

    private function sendNow(): void
    {
        /** @var Broadcast $broadcast */
        $broadcast = $this->getRecord();

        if ($broadcast->status !== BroadcastStatus::Scheduled) {
            return;
        }

        $broadcast->forceFill([
            'scheduled_at' => null,
            'status' => BroadcastStatus::Running,
        ])->save();

        app(BroadcastService::class)->dispatch($broadcast);

        Notification::make()
            ->title(__('filament-max-broadcasts::broadcasts.notifications.broadcast_started'))
            ->success()
            ->send();
    }

    private function cancel(): void
    {
        /** @var Broadcast $broadcast */
        $broadcast = $this->getRecord();

        if (! in_array($broadcast->status, [BroadcastStatus::Scheduled, BroadcastStatus::Running], true)) {
            return;
        }

        $broadcast->forceFill(['status' => BroadcastStatus::Cancelled])->save();

        Notification::make()
            ->title(__('filament-max-broadcasts::broadcasts.notifications.broadcast_cancelled'))
            ->warning()
            ->send();
    }
}
