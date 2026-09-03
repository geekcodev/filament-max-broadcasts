<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Jobs;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News;
use GeekCo\FilamentMaxBroadcasts\Events\BroadcastCompleted;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendBroadcastJob;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastRecipient;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSender;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\MaxPhpClient\Dto\Recipient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use RuntimeException;

class SendBroadcastJobTest extends TestCase
{
    private function createBroadcast(BroadcastStatus $status): Broadcast
    {
        return Broadcast::query()->create([
            'text' => 'Hello',
            'type' => 'news',
            'status' => $status,
            'total_recipients' => 2,
        ]);
    }

    private function createRecipient(Broadcast $broadcast, int $userId, int $chatId): BroadcastRecipient
    {
        return BroadcastRecipient::query()->create([
            'broadcast_id' => $broadcast->id,
            'user_id' => $userId,
            'chat_id' => $chatId,
            'status' => BroadcastRecipientStatus::Pending,
        ]);
    }

    public function testHandleSendsToAllPendingRecipientsAndCompletes(): void
    {
        Event::fake([BroadcastCompleted::class]);

        $broadcast = $this->createBroadcast(BroadcastStatus::Running);
        $this->createRecipient($broadcast, 1, 11);
        $this->createRecipient($broadcast, 2, 22);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->once()->with(
            Mockery::on(
                static fn (Recipient $recipient): bool => $recipient->userId === 1 && $recipient->chatId === 11,
            ),
            'Hello',
            [],
            News::News,
        );
        $sender->shouldReceive('send')->once()->with(
            Mockery::on(
                static fn (Recipient $recipient): bool => $recipient->userId === 2 && $recipient->chatId === 22,
            ),
            'Hello',
            [],
            News::News,
        );

        (new SendBroadcastJob($broadcast))->handle($sender);

        $broadcast->refresh();

        self::assertSame(BroadcastStatus::Completed, $broadcast->status);
        self::assertNotNull($broadcast->sent_at);
        self::assertSame(2, $broadcast->delivered_count);
        self::assertSame(0, $broadcast->failed_count);
        self::assertSame(2, $broadcast->recipients()->where('status', BroadcastRecipientStatus::Sent)->count());

        Event::assertDispatched(BroadcastCompleted::class);
    }

    public function testHandleMarksFailedRecipients(): void
    {
        $broadcast = $this->createBroadcast(BroadcastStatus::Running);
        $this->createRecipient($broadcast, 1, 11);
        $this->createRecipient($broadcast, 2, 22);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->once()->withArgs(
            static fn (Recipient $recipient): bool => $recipient->userId === 1,
        );
        $sender->shouldReceive('send')->once()->andThrow(new RuntimeException('MAX API down'));

        (new SendBroadcastJob($broadcast))->handle($sender);

        $sent = $broadcast->recipients()->where('user_id', 1)->first();
        $failed = $broadcast->recipients()->where('user_id', 2)->first();

        self::assertNotNull($sent);
        self::assertNotNull($failed);
        self::assertSame(BroadcastRecipientStatus::Sent, $sent->status);
        self::assertSame(BroadcastRecipientStatus::Failed, $failed->status);
        self::assertSame('MAX API down', $failed->error);
    }

    public function testHandleSkipsCancelledBroadcast(): void
    {
        $broadcast = $this->createBroadcast(BroadcastStatus::Cancelled);
        $this->createRecipient($broadcast, 1, 11);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldNotReceive('send');

        (new SendBroadcastJob($broadcast))->handle($sender);

        $broadcast->refresh();

        self::assertSame(BroadcastStatus::Cancelled, $broadcast->status);
        self::assertNotNull($broadcast->recipients()->first());
        self::assertSame(BroadcastRecipientStatus::Pending, $broadcast->recipients()->first()->status);
    }

    public function testHandleSkipsWhenLockIsHeld(): void
    {
        $broadcast = $this->createBroadcast(BroadcastStatus::Scheduled);
        $this->createRecipient($broadcast, 1, 11);

        $lock = Cache::lock("broadcast:{$broadcast->id}", 600);
        self::assertTrue($lock->get());

        try {
            $sender = $this->mock(BroadcastSender::class);
            $sender->shouldNotReceive('send');

            (new SendBroadcastJob($broadcast))->handle($sender);
        } finally {
            $lock->release();
        }

        $broadcast->refresh();

        self::assertSame(BroadcastStatus::Scheduled, $broadcast->status);
    }

    public function testFailedMarksBroadcastFailed(): void
    {
        $broadcast = $this->createBroadcast(BroadcastStatus::Running);

        (new SendBroadcastJob($broadcast))->failed(new RuntimeException('boom'));

        $broadcast->refresh();

        self::assertSame(BroadcastStatus::Failed, $broadcast->status);
    }
}
