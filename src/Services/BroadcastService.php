<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendBroadcastJob;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypes;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\UploadType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class BroadcastService
{
    public function __construct(
        private readonly BroadcastTextSanitizer $sanitizer,
        private readonly BroadcastRecipientsResolver $resolver,
    ) {
    }

    /**
     * @param  list<array{upload_type: string, path: string}>  $attachments
     */
    public function create(
        string $text,
        ?CarbonInterface $scheduledAt,
        ?Model $creator = null,
        array $attachments = [],
        string $type = News::News->value,
    ): Broadcast {
        if (! BroadcastTypes::contains($type)) {
            throw new InvalidArgumentException(sprintf('Unknown broadcast type "%s".', $type));
        }

        $chats = $this->resolver->resolve();

        $isFuture = $scheduledAt !== null && $scheduledAt->isFuture();

        $broadcast = Broadcast::query()->create([
            'text' => $this->sanitizer->sanitize($text),
            'type' => $type,
            'scheduled_at' => $scheduledAt,
            'status' => $isFuture ? BroadcastStatus::Scheduled : BroadcastStatus::Running,
            'total_recipients' => $chats->count(),
            'created_by' => $creator?->getKey(),
        ]);

        $this->saveAttachments($broadcast, $attachments);

        $recipientsData = $chats->map(
            static fn (MaxChat $chat): array => [
                'user_id' => $chat->getAttribute('user_id'),
                'chat_id' => $chat->getAttribute('chat_id'),
                'status' => BroadcastRecipientStatus::Pending,
            ],
        )->all();

        $broadcast->recipients()->createMany($recipientsData);

        $this->dispatch($broadcast);

        return $broadcast;
    }

    /**
     * @param  list<array{upload_type: string, path: string}>  $attachments
     */
    private function saveAttachments(Broadcast $broadcast, array $attachments): void
    {
        $rows = [];

        foreach ($attachments as $index => $attachment) {
            $uploadType = $attachment['upload_type'];
            $path = $attachment['path'];

            if (UploadType::tryFrom($uploadType) === null || trim($path) === '') {
                throw new InvalidArgumentException(sprintf('Invalid broadcast attachment #%d.', $index));
            }

            $rows[] = [
                'upload_type' => $uploadType,
                'path' => $path,
                'sort_order' => $index,
            ];
        }

        if ($rows !== []) {
            $broadcast->attachments()->createMany($rows);
        }
    }

    public function dispatch(Broadcast $broadcast): void
    {
        $pendingDispatch = SendBroadcastJob::dispatch($broadcast);

        if ($broadcast->scheduled_at !== null && $broadcast->scheduled_at->isFuture()) {
            $pendingDispatch->delay($broadcast->scheduled_at);
        }
    }
}
