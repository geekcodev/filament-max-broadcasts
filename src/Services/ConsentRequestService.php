<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Jobs\SendConsentRequestsJob;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Database\Eloquent\Collection;

/**
 * Запрос согласия по кнопке «Запросить согласие» в сегменте.
 *
 * Опрашиваются получатели самого сегмента: активные диалоги из chat_ids, которые ещё
 * не ответили на запрос по этому сегменту. Ответившие (и opt_in, и opt_out) запрос
 * повторно не получают; проигнорировавшие — получают. Состав сегмента ответами не
 * меняется, согласие хранится в max_broadcast_consents.
 *
 * Отправка — в очереди (SendConsentRequestsJob), сервис считает кандидатов и ставит задачу.
 */
class ConsentRequestService
{
    public function __construct(
        private readonly BroadcastRecipientsResolver $resolver,
        private readonly ConsentService $consentService,
    ) {
    }

    public function findSegment(int $segmentId): ?BroadcastSegment
    {
        return $this->consentService->findSegment($segmentId);
    }

    /**
     * Получатели сегмента (активные диалоги), которые ещё не ответили.
     *
     * @return Collection<int, MaxChat>
     */
    public function eligibleChats(BroadcastSegment $segment): Collection
    {
        /** @var int $segmentId */
        $segmentId = $segment->getKey();

        $answered = array_fill_keys($this->consentService->answeredChatIds($segmentId), true);
        $members = array_values(array_unique(array_map('intval', $segment->chat_ids ?? [])));

        if ($members === []) {
            return new Collection();
        }

        return $this->resolver
            ->resolveForChatIds($members)
            ->filter(
                static function (MaxChat $chat) use ($answered): bool {
                    /** @var int $chatId */
                    $chatId = $chat->getAttribute('chat_id');

                    return ! isset($answered[$chatId])
                        && ChatRegistry::chatType($chat) === ChatType::Dialog->value;
                },
            )
            ->values();
    }

    /**
     * @return list<int>
     */
    public function eligibleChatIds(BroadcastSegment $segment): array
    {
        return array_values($this->eligibleChats($segment)
            ->map(
                static function (MaxChat $chat): int {
                    /** @var int $chatId */
                    $chatId = $chat->getAttribute('chat_id');

                    return $chatId;
                },
            )
            ->all());
    }

    /**
     * Ставит в очередь запрос согласия для сегмента всем ещё не ответившим его получателям.
     *
     * @return int число получателей (0 — некого опрашивать)
     */
    public function sendRequest(BroadcastSegment $segment): int
    {
        $count = count($this->eligibleChatIds($segment));

        if ($count > 0) {
            SendConsentRequestsJob::dispatch($segment->id);
        }

        return $count;
    }
}
