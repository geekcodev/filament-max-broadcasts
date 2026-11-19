<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendBroadcastJob;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastAttachment;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypes;
use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\ChatType;
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
        private readonly ?ConsentService $consent = null,
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

        if (($chatIds === null || $chatIds === []) && $segments === []) {
            throw new InvalidArgumentException('Broadcast requires at least one segment or recipient.');
        }

        $this->validateAttachments($attachments);

        $chats = $this->resolveChats($chatIds, $segments);

        if ($chats->isEmpty()) {
            throw new InvalidArgumentException('Broadcast has no active recipients.');
        }

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
                'recipient_chat_ids' => $chatIds === null || $chatIds === [] ? null : $chats->map(
                    static function (MaxChat $chat): int {
                        /** @var int $chatId */
                        $chatId = $chat->getAttribute('chat_id');

                        return $chatId;
                    },
                )->values()->all(),
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
     * Диалоги приходят только из сегментов (согласившиеся, opt_in); вручную выбираются только
     * группы и каналы. Явный диалог в chatIds игнорируется.
     *
     * 1. С сегментами — объединение согласившихся диалогов и выбранных вручную групп/каналов.
     * 2. Без сегментов — только выбранные вручную группы/каналы.
     *
     * @param  list<int>|null  $chatIds
     * @param  list<BroadcastSegment>  $segments
     *
     * @return \Illuminate\Support\Collection<int, MaxChat>
     */
    private function resolveChats(?array $chatIds, array $segments): \Illuminate\Support\Collection
    {
        if ($segments !== []) {
            $consented = $this->consentedChatIds($segments);
            $manual = $chatIds === null ? [] : array_map('intval', $chatIds);
            $resolved = $this->resolver->resolveForChatIds(
                array_values(array_unique([...$consented, ...$manual])),
            );

            $ids = array_values(array_unique([
                ...$this->dialogIds($resolved, $consented),
                ...$this->nonDialogIds($resolved, $manual),
            ]));

            return $this->filterByChatIds($resolved, $ids)->values();
        }

        if ($chatIds !== null && $chatIds !== []) {
            $resolved = $this->resolver->resolveForChatIds(array_map('intval', $chatIds));

            return $this->filterByChatIds($resolved, $this->nonDialogIds($resolved, $chatIds));
        }

        // Недостижимо: create() требует сегменты или chatIds. Возвращаем пусто,
        // чтобы будущая ошибка не превратилась в рассылку всем активным чатам.
        return new \Illuminate\Support\Collection();
    }

    /**
     * Идентификаторы диалогов из списка (согласия по сегментам относятся только к диалогам).
     *
     * @param  \Illuminate\Support\Collection<int, MaxChat>  $chats
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function dialogIds(\Illuminate\Support\Collection $chats, array $ids): array
    {
        $wanted = array_flip($ids);

        return array_values(array_unique(array_map(
            static function (MaxChat $chat): int {
                /** @var int $chatId */
                $chatId = $chat->getAttribute('chat_id');

                return $chatId;
            },
            $chats
                ->filter(static function (MaxChat $chat) use ($wanted): bool {
                    /** @var int $chatId */
                    $chatId = $chat->getAttribute('chat_id');

                    return isset($wanted[$chatId])
                        && ChatRegistry::chatType($chat) === ChatType::Dialog->value;
                })
                ->all(),
        )));
    }

    /**
     * Идентификаторы групп и каналов из явного списка (диалоги отбрасываются).
     *
     * @param  \Illuminate\Support\Collection<int, MaxChat>  $chats
     * @param  list<int>|null  $chatIds
     * @return list<int>
     */
    private function nonDialogIds(\Illuminate\Support\Collection $chats, ?array $chatIds): array
    {
        if ($chatIds === null || $chatIds === []) {
            return [];
        }

        $ids = array_map('intval', $chatIds);

        return array_values(array_unique(array_map(
            static function (MaxChat $chat): int {
                /** @var int $chatId */
                $chatId = $chat->getAttribute('chat_id');

                return $chatId;
            },
            $chats
                ->filter(static function (MaxChat $chat) use ($ids): bool {
                    /** @var int $chatId */
                    $chatId = $chat->getAttribute('chat_id');
                    $type = ChatRegistry::chatType($chat);

                    return in_array($chatId, $ids, true)
                        && in_array($type, [ChatType::Chat->value, ChatType::Channel->value], true);
                })
                ->all(),
        )));
    }

    private function consentService(): ConsentService
    {
        return $this->consent ?? new ConsentService();
    }

    /**
     * Объединение согласившихся (opt_in) чатов выбранных сегментов.
     *
     * @param  list<BroadcastSegment>  $segments
     * @return list<int>
     */
    private function consentedChatIds(array $segments): array
    {
        if ($segments === []) {
            return [];
        }

        $segmentIds = array_map(
            static function (BroadcastSegment $segment): int {
                /** @var int $id */
                $id = $segment->getKey();

                return $id;
            },
            $segments,
        );

        $ids = [];

        foreach ($this->consentService()->optedInChatIdsBySegment($segmentIds) as $chatIds) {
            $ids = [...$ids, ...$chatIds];
        }

        return array_values(array_unique($ids));
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

    /**
     * Повтор рассылки тем же людям с учётом свежих согласий.
     *
     * Снимок получателей передаётся как есть, а резолв применяет актуальные правила:
     * диалоги — только согласившиеся в текущих сегментах (свежие отказы исключаются),
     * группы и каналы из снимка сохраняются. Если снимка нет, получатели резолвятся
     * по сегментам заново.
     *
     * @throws InvalidArgumentException когда после фильтра не осталось получателей
     */
    public function repeat(Broadcast $broadcast, ?Model $creator = null): Broadcast
    {
        $attachments = array_values($broadcast->attachments->map(
            static fn (BroadcastAttachment $attachment): array => [
                'upload_type' => $attachment->upload_type->value,
                'path' => $attachment->path,
            ],
        )->all());

        /** @var list<BroadcastSegment> $segments */
        $segments = array_values($broadcast->segments->all());

        return $this->create(
            text: $broadcast->text,
            scheduledAt: null,
            creator: $creator,
            attachments: $attachments,
            type: $broadcast->type,
            chatIds: $broadcast->recipient_chat_ids,
            segments: $segments,
        );
    }
}
