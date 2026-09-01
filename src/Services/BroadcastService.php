<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastType;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendBroadcastJob;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class BroadcastService
{
    public function __construct(
        private readonly BroadcastTextSanitizer $sanitizer,
        private readonly BroadcastRecipientsResolver $resolver,
    ) {
    }

    public function create(
        string $text,
        ?CarbonInterface $scheduledAt,
        ?Model $creator = null,
        ?string $imagePath = null,
        BroadcastType $type = BroadcastType::News,
    ): Broadcast {
        $chats = $this->resolver->resolve();

        $isFuture = $scheduledAt !== null && $scheduledAt->isFuture();

        $broadcast = Broadcast::query()->create([
            'text' => $this->sanitizer->sanitize($text),
            'type' => $type,
            'image_path' => $imagePath,
            'scheduled_at' => $scheduledAt,
            'status' => $isFuture ? BroadcastStatus::Scheduled : BroadcastStatus::Running,
            'total_recipients' => $chats->count(),
            'created_by' => $creator?->getKey(),
        ]);

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

    public function dispatch(Broadcast $broadcast): void
    {
        $pendingDispatch = SendBroadcastJob::dispatch($broadcast);

        if ($broadcast->scheduled_at !== null && $broadcast->scheduled_at->isFuture()) {
            $pendingDispatch->delay($broadcast->scheduled_at);
        }
    }
}
