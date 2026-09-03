<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Jobs;

use GeekCo\FilamentMaxBroadcasts\Contracts\BroadcastTypeContract;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Events\BroadcastCompleted;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastAttachment;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastRecipient;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSender;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastTextSanitizer;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypes;
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

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    /** @var list<int> */
    public array $backoff;

    public function __construct(public Broadcast $broadcast)
    {
        $this->tries = config()->integer('filament-max-broadcasts.queue.tries', 3);
        $this->timeout = config()->integer('filament-max-broadcasts.queue.timeout', 3600);

        $backoff = [];

        foreach (config()->array('filament-max-broadcasts.queue.backoff', [60, 300]) as $value) {
            $backoff[] = is_numeric($value) ? (int) $value : 0;
        }

        $this->backoff = $backoff;
    }

    public function handle(BroadcastSender $sender): void
    {
        $lockTtl = config()->integer('filament-max-broadcasts.queue.lock_ttl_seconds', 600);

        $lock = Cache::lock("broadcast:{$this->broadcast->id}", $lockTtl);

        if (! $lock->get()) {
            Log::warning('Broadcast: send job skipped, lock held by another job.', [
                'broadcast_id' => $this->broadcast->id,
            ]);

            $this->delete();

            return;
        }

        try {
            $this->send($sender);
        } finally {
            $lock->release();
        }
    }

    private function send(BroadcastSender $sender): void
    {
        $broadcast = $this->broadcast->fresh() ?? $this->broadcast;

        if ($broadcast->status === BroadcastStatus::Cancelled) {
            return;
        }

        $broadcast->forceFill([
            'status' => BroadcastStatus::Running,
            'sent_at' => $broadcast->sent_at ?? now(),
        ])->save();

        $type = BroadcastTypes::instance($broadcast->type);

        /** @var Collection<int, BroadcastRecipient> $recipients */
        $recipients = $broadcast->recipients()
            ->where('status', BroadcastRecipientStatus::Pending)
            ->get();

        $batchSize = config()->integer('filament-max-broadcasts.queue.batch_size', 25);

        foreach ($recipients as $index => $recipient) {
            if ($index > 0 && $index % $batchSize === 0 && $this->isCancelled($broadcast)) {
                return;
            }

            $this->sendTo($sender, $recipient, $type);

            if ($index > 0 && $index % $batchSize === 0) {
                $this->refreshCounters($broadcast);
            }
        }

        $this->refreshCounters($broadcast);

        if ($broadcast->recipients()->where('status', BroadcastRecipientStatus::Pending)->exists()) {
            return;
        }

        $broadcast->forceFill([
            'status' => BroadcastStatus::Completed,
            'sent_at' => now(),
        ])->save();

        BroadcastCompleted::dispatch($broadcast);
    }

    private function sendTo(BroadcastSender $sender, BroadcastRecipient $recipient, BroadcastTypeContract $type): void
    {
        /** @var list<array{upload_type: string, path: string}> $media */
        $media = $this->broadcast->attachments
            ->map(static fn (BroadcastAttachment $attachment): array => [
                'upload_type' => $attachment->upload_type->value,
                'path' => $attachment->path,
            ])
            ->values()
            ->all();

        try {
            $sender->send(
                new Recipient(chatId: $recipient->chat_id, userId: $recipient->user_id),
                app(BroadcastTextSanitizer::class)->toMaxHtml($this->broadcast->text),
                $media,
                $type,
            );
            $recipient->forceFill([
                'status' => BroadcastRecipientStatus::Sent,
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Broadcast: recipient send failed.', [
                'broadcast_id' => $this->broadcast->id,
                'user_id' => $recipient->user_id,
                'chat_id' => $recipient->chat_id,
                'error' => $exception->getMessage(),
            ]);
            $recipient->forceFill([
                'status' => BroadcastRecipientStatus::Failed,
                'error' => $exception->getMessage(),
            ])->save();
        }
    }

    private function isCancelled(Broadcast $broadcast): bool
    {
        return $broadcast->fresh()?->status === BroadcastStatus::Cancelled;
    }

    private function refreshCounters(Broadcast $broadcast): void
    {
        $broadcast->forceFill([
            'delivered_count' => $broadcast->recipients()
                ->where('status', BroadcastRecipientStatus::Sent)
                ->count(),
            'failed_count' => $broadcast->recipients()
                ->where('status', BroadcastRecipientStatus::Failed)
                ->count(),
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendBroadcastJob failed', [
            'broadcast_id' => $this->broadcast->id,
            'error' => $exception->getMessage(),
        ]);

        $broadcast = $this->broadcast->fresh();

        if ($broadcast !== null) {
            $broadcast->forceFill(['status' => BroadcastStatus::Failed])->save();
        }
    }
}
