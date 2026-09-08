<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Listeners;

use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\LaravelMaxClient\Webhook\MaxUpdateReceived;
use GeekCo\MaxPhpClient\ApiClient;
use GeekCo\MaxPhpClient\Dto\NewMessageBody;
use GeekCo\MaxPhpClient\Dto\Recipient;
use GeekCo\MaxPhpClient\Enum\TextFormat;
use GeekCo\MaxPhpClient\Enum\UpdateType;
use GeekCo\MaxPhpClient\Exception\MaxApiException;
use Illuminate\Support\Facades\Log;

/**
 * Обработка нажатий на callback-кнопки согласия («Согласен» / «Не согласен»).
 *
 * Значение payload: consent:<action>, где action ∈ {opt_in, opt_out}. Чужие
 * callback'и и payload'ы игнорируются (не отвечаем); вызовы MAX логируются без
 * чувствительных данных (callback_id, payload), как требует laravel-max-client.
 *
 * После обработки: убирает inline-кнопки из исходного сообщения (editMessage,
 * текст опроса сохраняется), отправляет подтверждающее сообщение в чат
 * (sendMessage) и отвечает на callback (sendAnswer).
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

        $confirmation = $action === 'opt_in'
            ? config()->string('filament-max-broadcasts.consent.answer_notification_opt_in', 'Вы согласились на получение рассылок.')
            : config()->string('filament-max-broadcasts.consent.answer_notification_opt_out', 'Вы отказались от получения рассылок.');

        $pollText = $callback->message?->body?->text;
        $pollFormat = $callback->message?->body->format ?? TextFormat::Html;

        $this->removeButtons($update->messageId ?? $callback->message?->body?->mid, $pollText, $pollFormat);
        $this->sendConfirmation($chatId, $confirmation);
        $this->acknowledgeCallback($callback->callbackId, $confirmation);
    }

    private function removeButtons(?string $messageId, ?string $text, ?TextFormat $format): void
    {
        if ($messageId === null || $text === null || trim($text) === '') {
            Log::warning('MAX consent edit message skipped (message text unavailable)');

            return;
        }

        try {
            $this->api->editMessage($messageId, NewMessageBody::create(text: $text, format: $format));
        } catch (MaxApiException $exception) {
            Log::warning('MAX consent edit message failed (buttons removal)', [
                'message_id' => $messageId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sendConfirmation(int $chatId, string $text): void
    {
        try {
            $this->api->sendMessage(
                new Recipient(chatId: $chatId),
                NewMessageBody::create(text: $text, format: TextFormat::Html),
            );
        } catch (MaxApiException $exception) {
            Log::error('MAX consent confirmation message failed', [
                'chat_id' => $chatId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function acknowledgeCallback(string $callbackId, string $notification): void
    {
        try {
            $this->api->sendAnswer($callbackId, notification: $notification);
        } catch (MaxApiException $exception) {
            Log::error('MAX consent answer failed', [
                'callback_id' => $callbackId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
