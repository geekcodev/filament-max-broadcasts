<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendBroadcastJob;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypes;
use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\UploadType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
     * @param  list<int>|null  $chatIds  Явно выбранные получатели (chat_id). Null — резолвим автоматически.
     * @param  list<BroadcastSegment>  $segments  Сегменты получателей (опционально, для привязки к рассылке)
     */
    public function create(
        string $text,
        ?CarbonInterface $scheduledAt,
        ?Model $creator = null,
        array $attachments = [],
        string $type = News::News->value,
        ?array $chatIds = null,
        array $segments = [],
    ): Broadcast {
        if (! BroadcastTypes::contains($type)) {
            throw new InvalidArgumentException(sprintf('Unknown broadcast type "%s".', $type));
        }

        $this->validateAttachments($attachments);

        $chats = $this->resolveChats($chatIds, $segments);

        $isFuture = $scheduledAt !== null && $scheduledAt->isFuture();

        $broadcast = DB::transaction(function () use (
            $text,
            $scheduledAt,
            $creator,
            $attachments,
            $type,
            $chatIds,
            $segments,
            $chats,
            $isFuture,
        ): Broadcast {
            $broadcast = Broadcast::query()->create([
                'text' => $this->sanitizer->sanitize($text),
                'type' => $type,
                'scheduled_at' => $scheduledAt,
                'status' => $isFuture ? BroadcastStatus::Scheduled : BroadcastStatus::Running,
                'total_recipients' => $chats->count(),
                'created_by' => $creator?->getKey(),
                'recipient_chat_ids' => $chatIds === null || $chatIds === [] ? null : $chatIds,
            ]);

            if ($segments !== []) {
                $broadcast->segments()->sync(array_map(
                    static fn (BroadcastSegment $segment): int => $segment->id,
                    $segments,
                ));
            }

            $this->saveAttachments($broadcast, $attachments);

            $recipientsData = $chats->map(
                static fn (MaxChat $chat): array => [
                    'user_id' => ChatRegistry::userId($chat),
                    'chat_id' => $chat->getAttribute('chat_id'),
                    'status' => BroadcastRecipientStatus::Pending,
                ],
            )->all();

            $broadcast->recipients()->createMany($recipientsData);

            return $broadcast;
        });

        $this->dispatch($broadcast);

        return $broadcast;
    }

    /**
     * @param  list<array{upload_type: string, path: string}>  $attachments
     */
    private function validateAttachments(array $attachments): void
    {
        foreach ($attachments as $index => $attachment) {
            $uploadType = $attachment['upload_type'];
            $path = $attachment['path'];

            if (UploadType::tryFrom($uploadType) === null || trim($path) === '') {
                throw new InvalidArgumentException(sprintf('Invalid broadcast attachment #%d.', $index));
            }
        }
    }

    /**
     * Резолвим конкретный список получателей для рассылки.
     *
     * Приоритет:
     * 1. Явно переданные chatIds — фильтруем по ним.
     * 2. Иначе, если заданы сегменты — берём объединение их chat_ids.
     * 3. Иначе — все активные чаты (дефолт).
     *
     * @param  list<int>|null  $chatIds
     * @param  list<BroadcastSegment>  $segments
     *
     * @return \Illuminate\Support\Collection<int, MaxChat>
     */
    private function resolveChats(?array $chatIds, array $segments): \Illuminate\Support\Collection
    {
        $resolved = $this->resolver->resolve();

        if ($chatIds !== null && $chatIds !== []) {
            $ids = array_map('intval', $chatIds);

            return $this->filterByChatIds($resolved, $ids);
        }

        if ($segments !== []) {
            $ids = [];

            foreach ($segments as $segment) {
                $ids = [...$ids, ...array_map('intval', $segment->chat_ids ?? [])];
            }

            $ids = array_values(array_unique($ids));

            return $this->filterByChatIds($resolved, $ids);
        }

        return $resolved;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MaxChat>  $chats
     * @param  list<int>  $ids
     *
     * @return \Illuminate\Support\Collection<int, MaxChat>
     */
    private function filterByChatIds(\Illuminate\Support\Collection $chats, array $ids): \Illuminate\Support\Collection
    {
        return $chats->filter(
            static function (MaxChat $chat) use ($ids): bool {
                /** @var int $chatId */
                $chatId = $chat->getAttribute('chat_id');

                return in_array($chatId, $ids, true);
            },
        )->values();
    }

    /**
     * @param  list<array{upload_type: string, path: string}>  $attachments
     */
    private function saveAttachments(Broadcast $broadcast, array $attachments): void
    {
        $rows = [];

        foreach ($attachments as $index => $attachment) {
            $rows[] = [
                'upload_type' => $attachment['upload_type'],
                'path' => $attachment['path'],
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
