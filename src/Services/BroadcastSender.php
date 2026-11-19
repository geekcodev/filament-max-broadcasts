<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Contracts\BroadcastTypeContract;
use GeekCo\FilamentMaxBroadcasts\Support\AttachmentFile;
use GeekCo\FilamentMaxBroadcasts\Support\AttachmentStorage;
use GeekCo\MaxPhpClient\ApiClient;
use GeekCo\MaxPhpClient\Dto\AttachmentRequest;
use GeekCo\MaxPhpClient\Dto\AttachmentRequest\AudioAttachmentRequest;
use GeekCo\MaxPhpClient\Dto\AttachmentRequest\FileAttachmentRequest;
use GeekCo\MaxPhpClient\Dto\AttachmentRequest\InlineKeyboardAttachmentRequest;
use GeekCo\MaxPhpClient\Dto\AttachmentRequest\PhotoAttachmentRequest;
use GeekCo\MaxPhpClient\Dto\AttachmentRequest\VideoAttachmentRequest;
use GeekCo\MaxPhpClient\Dto\InlineKeyboardAttachmentRequestPayload;
use GeekCo\MaxPhpClient\Dto\NewMessageBody;
use GeekCo\MaxPhpClient\Dto\PhotoAttachmentRequestPayload;
use GeekCo\MaxPhpClient\Dto\Recipient;
use GeekCo\MaxPhpClient\Dto\UploadedInfo;
use GeekCo\MaxPhpClient\Enum\TextFormat;
use GeekCo\MaxPhpClient\Enum\UploadType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Единственная точка отправки рассылки в MAX: текст (HTML), медиавложения, кнопки-диплинки.
 * Прямые вызовы ApiClient из Filament/Page запрещены — только через этот сервис.
 */
class BroadcastSender
{
    /**
     * Локальные копии вложений на время жизни отправителя: удалённый файл скачивается
     * один раз на рассылку, а не на каждого получателя. Временные копии удаляются в
     * деструкторе.
     *
     * @var array<string, AttachmentFile>
     */
    private array $localFiles = [];

    public function __construct(
        private readonly ApiClient $api,
    ) {
    }

    public function __destruct()
    {
        foreach ($this->localFiles as $file) {
            $file->cleanup();
        }

        $this->localFiles = [];
    }

    /**
     * Отправляет рассылку одному получателю.
     *
     * @param  list<array{upload_type: string, path: string}>  $media
     * @param  list<list<\GeekCo\MaxPhpClient\Dto\Button>>|null  $buttonRows  кнопки вместо кнопок типа (запрос согласия по сегменту)
     */
    public function send(
        Recipient $recipient,
        string $text,
        array $media,
        ?BroadcastTypeContract $type = null,
        ?array $buttonRows = null,
    ): void {
        $attachments = [];

        foreach ($media as $item) {
            $uploadType = UploadType::tryFrom($item['upload_type']);

            if ($uploadType === null || trim($item['path']) === '') {
                continue;
            }

            $attachments[] = $this->uploadMedia($uploadType, $item['path']);
        }

        $rows = $buttonRows ?? $type?->buttonRows() ?? [];

        if ($rows !== []) {
            $attachments[] = new InlineKeyboardAttachmentRequest(
                new InlineKeyboardAttachmentRequestPayload($rows),
            );
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

    private function uploadMedia(UploadType $type, string $path): AttachmentRequest
    {
        return $this->uploadToAttachment($type, $this->localFile($path)->path(), $path);
    }

    private function localFile(string $path): AttachmentFile
    {
        $disk = AttachmentStorage::disk();
        $key = $disk.':'.$path;

        return $this->localFiles[$key] ??= AttachmentFile::fromDisk(Storage::disk($disk), $path);
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
            'has_token' => $result->token !== null,
        ]);

        if ($type === UploadType::Image) {
            return new PhotoAttachmentRequest(new PhotoAttachmentRequestPayload(
                token: $result->token,
                url: $result->token === null ? $result->url : null,
            ));
        }

        $token = $result->token;

        if ($token === null) {
            throw new \RuntimeException('MAX upload did not return an attachment token.');
        }

        $payload = new UploadedInfo($token);

        return match ($type) {
            UploadType::Video => new VideoAttachmentRequest($payload),
            UploadType::Audio => new AudioAttachmentRequest($payload),
            UploadType::File => new FileAttachmentRequest($payload),
        };
    }
}
