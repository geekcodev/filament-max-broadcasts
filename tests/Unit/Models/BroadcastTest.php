<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Models;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastRecipient;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class BroadcastTest extends TestCase
{
    public function testUsesMaxBroadcastsTable(): void
    {
        self::assertSame('max_broadcasts', (new Broadcast())->getTable());
    }

    public function testCasts(): void
    {
        $broadcast = Broadcast::query()->create([
            'text' => 'Hello',
            'type' => 'news',
            'status' => BroadcastStatus::Scheduled,
            'scheduled_at' => now()->addHour(),
            'total_recipients' => 5,
            'delivered_count' => 2,
            'failed_count' => 1,
        ]);

        self::assertSame('news', $broadcast->type);
        self::assertSame(BroadcastStatus::Scheduled, $broadcast->status);
        self::assertSame(5, $broadcast->total_recipients);
        self::assertSame(2, $broadcast->delivered_count);
        self::assertSame(1, $broadcast->failed_count);
    }

    public function testCreatorRelation(): void
    {
        $user = TestUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);

        $broadcast = Broadcast::query()->create([
            'text' => 'Hello',
            'type' => 'news',
            'status' => BroadcastStatus::Scheduled,
            'created_by' => $user->id,
        ]);

        self::assertNotNull($broadcast->creator);

        /** @var TestUser $creator */
        $creator = $broadcast->creator;
        self::assertSame($user->id, $creator->id);
    }

    public function testRecipientsRelation(): void
    {
        $broadcast = Broadcast::query()->create([
            'text' => 'Hello',
            'type' => 'news',
            'status' => BroadcastStatus::Running,
        ]);

        $recipient = BroadcastRecipient::query()->create([
            'broadcast_id' => $broadcast->id,
            'chat_id' => 111,
            'user_id' => 222,
            'status' => 'pending',
        ]);

        self::assertTrue($broadcast->recipients->contains($recipient));
    }
}
