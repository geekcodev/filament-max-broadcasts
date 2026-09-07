<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Listeners;

use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\LaravelMaxClient\Webhook\MaxUpdateReceived;
use GeekCo\MaxPhpClient\ApiClient;
use GeekCo\MaxPhpClient\Enum\UpdateType;
use GeekCo\MaxPhpClient\Exception\MaxApiException;
use Illuminate\Support\Facades\Log;

/**
 * Обработка нажатий на callback-кнопки согласия («Согласен» / «Не согласен»).
 *
 * Значение payload: consent:<action>, где action ∈ {opt_in, opt_out}. Чужие
 * callback'и и payload'ы игнорируются (не отвечаем); вызовы MAX логируются без
 * чувствительных данных (callback_id, payload), как требует laravel-max-client.
 */
class HandleConsentCallback
{
    public function __construct(
        private readonly ApiClient $api,
        private readonly ConsentService $consent,
    ) {
    }

    public function handle(MaxUpdateReceived $event): void
    {
        $update = $event->update;

        if ($update->updateType !== UpdateType::MessageCallback) {
            return;
        }

        $callback = $update->callback;

        if ($callback === null || $callback->callbackId === '' || $callback->payload === null) {
            return;
        }

        $prefix = config()->string('filament-max-broadcasts.consent.payload_prefix', 'consent');

        if (! str_starts_with($callback->payload, $prefix.':')) {
            return;
        }

        $chatId = $update->chatId;
        $userId = $update->user?->userId;

        if ($chatId === null) {
            return;
        }

        $action = substr($callback->payload, strlen($prefix) + 1);

        match ($action) {
            'opt_in' => $this->consent->optIn($chatId, $userId),
            'opt_out' => $this->consent->optOut($chatId, $userId),
            default => null,
        };

        if (! in_array($action, ['opt_in', 'opt_out'], true)) {
            return;
        }

        $notification = config()->string('filament-max-broadcasts.consent.answer_notification', 'Спасибо! Ваш ответ учтён.');

        try {
            $this->api->sendAnswer($callback->callbackId, notification: $notification);
        } catch (MaxApiException $exception) {
            Log::error('MAX consent answer failed', [
                'chat_id' => $chatId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
