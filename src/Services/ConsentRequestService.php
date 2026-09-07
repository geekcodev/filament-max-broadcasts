<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Jobs\SendConsentRequestsJob;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Database\Eloquent\Collection;

/**
 * Отправка запроса согласия через кнопку на списке рассылок.
 *
 * Получатели запроса — активные чаты без записи согласия (ни opt_in, ни opt_out).
 * Факт получения не фиксируется: проигнорировавшие запрос получат его снова при
 * следующем запуске, а ответившие — никогда. Сама отправка — в очереди
 * (SendConsentRequestsJob), сервис считает кандидатов и ставит задачу.
 */
class ConsentRequestService
{
    public function __construct(
        private readonly BroadcastRecipientsResolver $resolver,
        private readonly ConsentService $consentService,
    ) {
    }

    /**
     * Активные чаты без записей согласия.
     *
     * @return Collection<int, MaxChat>
     */
    public function eligibleChats(): Collection
    {
        $answered = $this->consentService->answeredChatIds();
        $answered = array_fill_keys($answered, true);

        return $this->resolver
            ->resolve()
            ->filter(
                static function (MaxChat $chat) use ($answered): bool {
                    /** @var int $chatId */
                    $chatId = $chat->getAttribute('chat_id');

                    return ! isset($answered[$chatId]);
                },
            )
            ->values();
    }

    /**
     * @return list<int>
     */
    public function eligibleChatIds(): array
    {
        return array_values($this->eligibleChats()
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
     * Ставит в очередь рассылку запроса согласия всем ещё не ответившим.
     *
     * @return int число получателей (0 — некого опрашивать)
     */
    public function sendRequest(): int
    {
        $count = count($this->eligibleChatIds());

        if ($count > 0) {
            SendConsentRequestsJob::dispatch();
        }

        return $count;
    }
}
