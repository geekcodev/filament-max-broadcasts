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
 * Обработка нажатий на callback-кнопки согласия («Да» / «Нет»).
 *
 * Payload всегда содержит сегмент: consent:<segmentId>:<action>, где
 * action ∈ {opt_in, opt_out}. Чужие callback'и, payload'ы без сегмента и
 * неизвестные сегменты игнорируются (не отвечаем); вызовы MAX логируются без
 * чувствительных данных (callback_id, payload), как требует laravel-max-client.
 *
 * После обработки: удаляет исходное сообщение с опросом (deleteMessage), отправляет
 * подтверждающее сообщение в чат (sendMessage) и отвечает на callback (sendAnswer).
 * Если удалить сообщение не удалось, кнопки убираются правкой (editMessage): опрос
 * нельзя нажать повторно, а согласие всё равно записано.
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
        $pattern = '/^'.preg_quote($prefix, '/').':(\d+):(opt_in|opt_out)$/';

        if (preg_match($pattern, $callback->payload, $matches) !== 1) {
            return;
        }

        $chatId = $update->chatId;
        $userId = $update->user?->userId;

        if ($chatId === null) {
            return;
        }

        $segmentId = (int) $matches[1];
        $action = $matches[2];

        if ($this->consent->findSegment($segmentId) === null) {
            Log::warning('MAX consent callback skipped (segment not found)', ['segment_id' => $segmentId]);

            return;
        }

        if ($action === 'opt_in') {
            $this->consent->optIn($chatId, $userId, $segmentId);
        } else {
            $this->consent->optOut($chatId, $userId, $segmentId);
        }

        $confirmation = $action === 'opt_in'
            ? config()->string('filament-max-broadcasts.consent.answer_notification_opt_in', 'Вы согласились на получение рассылок.')
            : config()->string('filament-max-broadcasts.consent.answer_notification_opt_out', 'Вы отказались от получения рассылок.');

        $pollText = $callback->message?->body?->text;
        $pollFormat = $callback->message?->body->format ?? TextFormat::Html;
        $messageId = $update->messageId ?? $callback->message?->body?->mid;

        $this->deletePollMessage($messageId, $pollText, $pollFormat);
        $this->sendConfirmation($chatId, $confirmation);
        $this->acknowledgeCallback($callback->callbackId, $confirmation);
    }

    private function deletePollMessage(?string $messageId, ?string $text, ?TextFormat $format): void
    {
        if ($messageId === null || trim($messageId) === '') {
            Log::warning('MAX consent delete message skipped (message id unavailable)');

            return;
        }

        try {
            $this->api->deleteMessage($messageId);

            return;
        } catch (MaxApiException $exception) {
            Log::warning('MAX consent delete message failed', [
                'message_id' => $messageId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        if ($text === null || trim($text) === '') {
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
