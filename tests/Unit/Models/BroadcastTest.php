<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Models;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastType;
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
            'type' => BroadcastType::News,
            'status' => BroadcastStatus::Scheduled,
            'scheduled_at' => now()->addHour(),
            'total_recipients' => 5,
            'delivered_count' => 2,
            'failed_count' => 1,
        ]);

        self::assertSame(BroadcastType::News, $broadcast->type);
        self::assertSame(BroadcastStatus::Scheduled, $broadcast->status);
        self::assertIsInt($broadcast->total_recipients);
        self::assertIsInt($broadcast->delivered_count);
        self::assertIsInt($broadcast->failed_count);
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
            'type' => BroadcastType::News,
            'status' => BroadcastStatus::Scheduled,
            'created_by' => $user->id,
        ]);

        self::assertSame($user->id, $broadcast->creator->id);
    }

    public function testRecipientsRelation(): void
    {
        $broadcast = Broadcast::query()->create([
            'text' => 'Hello',
            'type' => BroadcastType::News,
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
