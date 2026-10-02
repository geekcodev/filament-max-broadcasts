<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Models;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastRecipient;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;

class BroadcastRecipientTest extends TestCase
{
    public function testUsesMaxBroadcastRecipientsTable(): void
    {
        self::assertSame('max_broadcast_recipients', (new BroadcastRecipient())->getTable());
    }

    public function testCasts(): void
    {
        $broadcast = Broadcast::query()->create([
            'text' => 'Hello',
            'type' => 'news',
            'status' => 'running',
        ]);

        $recipient = BroadcastRecipient::query()->create([
            'broadcast_id' => $broadcast->id,
            'chat_id' => 111,
            'user_id' => 222,
            'status' => BroadcastRecipientStatus::Pending,
        ]);

        self::assertSame(BroadcastRecipientStatus::Pending, $recipient->status);
        self::assertIsInt($recipient->chat_id);
        self::assertIsInt($recipient->user_id);
    }

    public function testBroadcastRelation(): void
    {
        $broadcast = Broadcast::query()->create([
            'text' => 'Hello',
            'type' => 'news',
            'status' => 'running',
        ]);

        $recipient = BroadcastRecipient::query()->create([
            'broadcast_id' => $broadcast->id,
            'chat_id' => 111,
            'status' => 'pending',
        ]);

        self::assertNotNull($recipient->broadcast);
        self::assertSame($broadcast->id, $recipient->broadcast->id);
    }

    public function testMaxChatRelation(): void
    {
        $broadcast = Broadcast::query()->create([
            'text' => 'Hello',
            'type' => 'news',
            'status' => 'running',
        ]);

        Chats::create(111, 222, [
            'status' => 'active',
        ]);

        $recipient = BroadcastRecipient::query()->create([
            'broadcast_id' => $broadcast->id,
            'chat_id' => 111,
            'user_id' => 222,
            'status' => 'sent',
        ]);

        self::assertNotNull($recipient->maxChat);
        self::assertSame(111, ChatRegistry::chatId($recipient->maxChat));
    }

    public function testStoresNegativeGroupChatId(): void
    {
        // Группы и каналы MAX имеют отрицательные `chat_id`: колонка знаковая,
        // иначе MySQL отверг бы такую запись.
        $broadcast = Broadcast::query()->create([
            'text' => 'Hello',
            'type' => 'news',
            'status' => 'running',
        ]);

        $recipient = BroadcastRecipient::query()->create([
            'broadcast_id' => $broadcast->id,
            'chat_id' => -1234567890123,
            'user_id' => -987654321,
            'status' => BroadcastRecipientStatus::Pending,
        ]);

        $fresh = BroadcastRecipient::query()->findOrFail($recipient->id);

        self::assertSame(-1234567890123, $fresh->chat_id);
        self::assertSame(-987654321, $fresh->user_id);
    }
}
