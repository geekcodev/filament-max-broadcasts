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
 * Получатели запроса — активные диалоги без записи согласия именно в этом сегменте
 * (группы и каналы не опрашиваются: согласие личное). Факт получения не фиксируется:
 * проигнорировавшие запрос получат его снова, ответившие — никогда. Сама отправка —
 * в очереди (SendConsentRequestsJob), сервис считает кандидатов и ставит задачу.
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
     * Активные диалоги без записи согласия для сегмента.
     *
     * @return Collection<int, MaxChat>
     */
    public function eligibleChats(BroadcastSegment $segment): Collection
    {
        $answered = array_fill_keys($this->consentService->answeredChatIds($segment->id), true);

        return $this->resolver
            ->resolve()
            ->filter(
                static function (MaxChat $chat) use ($answered): bool {
                    /** @var int $chatId */
                    $chatId = $chat->getAttribute('chat_id');

                    return ChatRegistry::chatType($chat) === ChatType::Dialog->value
                        && ! isset($answered[$chatId]);
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
     * Ставит в очередь запрос согласия для сегмента всем ещё не ответившим диалогам.
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
