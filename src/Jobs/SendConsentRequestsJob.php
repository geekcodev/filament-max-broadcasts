<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Jobs;

use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSender;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastTextSanitizer;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentRequestService;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Support\ConsentButtons;
use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\FilamentMaxBroadcasts\Support\QueueLockTtl;
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
 * Фоновая отправка запроса согласия по сегменту всем ещё не ответившим диалогам.
 *
 * Запрос не создаёт Broadcast: получатели переопределяются на момент выполнения, а
 * lock на сегмент защищает от параллельных запусков кнопкой. Каждая успешная
 * отправка фиксируется в журнале согласий записью pending; ответ (opt_in/opt_out)
 * перезаписывает её, поэтому ответившие повторно не опрашиваются, а pending
 * остаётся кандидатом на повторный запрос.
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

    public function __construct(public int $segmentId)
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
        ConsentService $consents,
    ): void {
        $lockTtl = QueueLockTtl::seconds();

        $lock = Cache::lock('consent:send-request:'.$this->segmentId, $lockTtl);

        if (! $lock->get()) {
            Log::warning('Consent: request send skipped, lock held by another job.');

            // Намеренно delete(): держатель lock'а сам опросит всех кандидатов
            // (каждая джоба резолвит их на момент выполнения), повторная обработка
            // отсюда — лишние дубли запроса.
            $this->delete();

            return;
        }

        try {
            $this->send($sender, $service, $sanitizer, $consents);
        } finally {
            $lock->release();
        }
    }

    private function send(
        BroadcastSender $sender,
        ConsentRequestService $service,
        BroadcastTextSanitizer $sanitizer,
        ConsentService $consents,
    ): void {
        $segment = $service->findSegment($this->segmentId);

        if (! $segment instanceof BroadcastSegment) {
            Log::warning('Consent: request send skipped, segment not found.', ['segment_id' => $this->segmentId]);

            return;
        }

        /** @var Collection<int, MaxChat> $chats */
        $chats = $service->eligibleChats($segment);

        if ($chats->isEmpty()) {
            return;
        }

        $message = $sanitizer->toMaxHtml($this->requestText($segment));
        $buttonRows = ConsentButtons::forSegment($this->segmentId);
        $batchSize = config()->integer('filament-max-broadcasts.queue.batch_size', 25);

        foreach ($chats->chunk($batchSize) as $chunk) {
            /** @var MaxChat $chat */
            foreach ($chunk as $chat) {
                $this->sendTo($sender, $consents, $segment, $chat, $message, $buttonRows);
            }
        }
    }

    private function requestText(BroadcastSegment $segment): string
    {
        $template = config()->string(
            'filament-max-broadcasts.consent.request_message',
            'Вы согласны получать рассылку «:segment»?',
        );

        return str_replace(':segment', e($segment->name), $template);
    }

    /**
     * @param  list<list<\GeekCo\MaxPhpClient\Dto\Button>>  $buttonRows
     */
    private function sendTo(
        BroadcastSender $sender,
        ConsentService $consents,
        BroadcastSegment $segment,
        MaxChat $chat,
        string $message,
        array $buttonRows,
    ): void {
        /** @var int $chatId */
        $chatId = $chat->getAttribute('chat_id');
        $userId = ChatRegistry::userId($chat);

        try {
            $sender->send(
                new Recipient(chatId: $chatId, userId: $userId),
                $message,
                [],
                null,
                $buttonRows,
            );
        } catch (Throwable $exception) {
            Log::warning('Consent: request send failed.', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        // Журнал не должен ронять уже выполненную отправку: иначе ретрай джобы
        // продублирует запрос. Сегмент передан моделью — повторного SELECT на
        // каждого получателя нет. Пропуск записи виден в логе.
        try {
            $consents->requestSentForSegment($segment, $chatId, $userId);
        } catch (Throwable $exception) {
            Log::warning('Consent: request state not recorded.', [
                'chat_id' => $chatId,
                'segment_id' => $this->segmentId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendConsentRequestsJob failed', [
            'segment_id' => $this->segmentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
