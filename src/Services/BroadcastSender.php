<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastType;
use GeekCo\FilamentMaxBroadcasts\Support\PromoButtons;
use GeekCo\MaxPhpClient\ApiClient;
use GeekCo\MaxPhpClient\Dto\AttachmentRequest;
use GeekCo\MaxPhpClient\Dto\NewMessageBody;
use GeekCo\MaxPhpClient\Dto\Recipient;
use GeekCo\MaxPhpClient\Enum\AttachmentType;
use GeekCo\MaxPhpClient\Enum\TextFormat;
use GeekCo\MaxPhpClient\Enum\UploadType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Единственная точка отправки рассылки в MAX: текст (HTML), фото, промо-кнопки.
 * Прямые вызовы ApiClient из Filament/Page запрещены — только через этот сервис.
 */
class BroadcastSender
{
    public function __construct(private readonly ApiClient $api)
    {
    }

    /**
     * Отправляет рассылку одному получателю. Для типа «Акция» подставляет
     * кнопки-диплинки в мини-приложение (из конфига).
     */
    public function send(Recipient $recipient, string $text, ?string $imagePath, BroadcastType $type): void
    {
        $attachments = [];

        if ($imagePath !== null && trim($imagePath) !== '') {
            $attachments[] = $this->uploadImage($imagePath);
        }

        if ($type === BroadcastType::Promo) {
            $rows = app(PromoButtons::class)->rows();

            if ($rows !== []) {
                $attachments[] = AttachmentRequest::create(
                    type: AttachmentType::InlineKeyboard,
                    rows: $rows,
                );
            }
        }

        $this->api->sendMessage(
            recipient: $recipient,
            body: NewMessageBody::create(
                text: $text,
                attachments: $attachments === [] ? null : $attachments,
                format: TextFormat::Html,
            ),
        );
    }

    private function uploadImage(string $path): AttachmentRequest
    {
        $disk = config()->string('filament-max-broadcasts.image.disk', 'public');

        return $this->uploadToAttachment(UploadType::Image, Storage::disk($disk)->path($path), $path);
    }

    private function uploadToAttachment(UploadType $type, string $absolutePath, ?string $label = null): AttachmentRequest
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new \RuntimeException('Media file not found or not readable: '.$absolutePath);
        }

        try {
            $result = $this->api->uploadMedia($type, $absolutePath);
        } catch (\Throwable $e) {
            Log::error('MAX media upload failed', [
                'type' => $type->value,
                'path' => $label ?? $absolutePath,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'previous' => $e->getPrevious()?->getMessage(),
            ]);

            throw $e;
        }

        Log::debug('MAX media upload ok', [
            'type' => $type->value,
            'path' => $label ?? $absolutePath,
            'token' => $result->token,
            'url' => $result->url,
        ]);

        return AttachmentRequest::create(
            type: AttachmentType::Image,
            token: $result->token,
            url: $result->token === null ? $result->url : null,
        );
    }
}
