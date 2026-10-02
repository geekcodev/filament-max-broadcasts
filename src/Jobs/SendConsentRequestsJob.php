<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Jobs;

use GeekCo\FilamentMaxBroadcasts\Contracts\BroadcastTypeContract;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\ConsentPoll;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSender;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastTextSanitizer;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentRequestService;
use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Dto\Recipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Фоновая отправка запроса согласия всем ещё не ответившим.
 *
 * Запрос не создаёт Broadcast: факт получения сознательно не фиксируется
 * (требование пользователя — ответившие запрос больше не получают, а
 * проигнорировавшие получают снова). Получатели переопределяются на момент
 * выполнения, lock защищает от параллельных запусков кнопкой.
 */
class SendConsentRequestsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    /** @var list<int> */
    public array $backoff;

    public function __construct()
    {
        $this->tries = config()->integer('filament-max-broadcasts.queue.tries', 3);
        $this->timeout = config()->integer('filament-max-broadcasts.queue.timeout', 3600);

        $backoff = [];

        foreach (config()->array('filament-max-broadcasts.queue.backoff', [60, 300]) as $value) {
            $backoff[] = is_numeric($value) ? (int) $value : 0;
        }

        $this->backoff = $backoff;
    }

    public function handle(
        BroadcastSender $sender,
        ConsentRequestService $service,
        BroadcastTextSanitizer $sanitizer,
    ): void {
        $lockTtl = config()->integer('filament-max-broadcasts.queue.lock_ttl_seconds', 600);

        $lock = Cache::lock('consent:send-request', $lockTtl);

        if (! $lock->get()) {
            Log::warning('Consent: request send skipped, lock held by another job.');

            // Намеренно delete(): держатель lock'а сам опросит всех кандидатов
            // (каждая джоба резолвит их на момент выполнения), повторная обработка
            // отсюда — лишние дубли запроса.
            $this->delete();

            return;
        }

        try {
            $this->send($sender, $service, $sanitizer);
        } finally {
            $lock->release();
        }
    }

    private function send(
        BroadcastSender $sender,
        ConsentRequestService $service,
        BroadcastTextSanitizer $sanitizer,
    ): void {
        /** @var Collection<int, MaxChat> $chats */
        $chats = $service->eligibleChats();

        if ($chats->isEmpty()) {
            return;
        }

        $message = $sanitizer->toMaxHtml(
            config()->string('filament-max-broadcasts.consent.request_message', 'Согласны ли вы получать наши новости и акции?'),
        );
        $type = ConsentPoll::ConsentPoll;
        $batchSize = config()->integer('filament-max-broadcasts.queue.batch_size', 25);

        foreach ($chats->chunk($batchSize) as $chunk) {
            /** @var MaxChat $chat */
            foreach ($chunk as $chat) {
                $this->sendTo($sender, $chat, $message, $type);
            }
        }
    }

    private function sendTo(BroadcastSender $sender, MaxChat $chat, string $message, BroadcastTypeContract $type): void
    {
        /** @var int $chatId */
        $chatId = $chat->getAttribute('chat_id');
        $userId = ChatRegistry::userId($chat);

        try {
            $sender->send(
                new Recipient(chatId: $chatId, userId: $userId),
                $message,
                [],
                $type,
            );
        } catch (Throwable $exception) {
            Log::warning('Consent: request send failed.', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendConsentRequestsJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
