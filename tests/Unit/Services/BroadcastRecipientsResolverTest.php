<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Support\Carbon;

class BroadcastRecipientsResolverTest extends TestCase
{
    public function testResolvesActiveChatsSortedByLastActivity(): void
    {
        $old = MaxChat::query()->create([
            'user_id' => 1,
            'chat_id' => 11,
            'status' => MaxChatStatus::Active,
            'last_activity_at' => Carbon::parse('2026-01-01 10:00:00'),
        ]);
        $fresh = MaxChat::query()->create([
            'user_id' => 2,
            'chat_id' => 12,
            'status' => MaxChatStatus::Active,
            'last_activity_at' => Carbon::parse('2026-01-02 10:00:00'),
        ]);

        $chats = (new BroadcastRecipientsResolver())->resolve();

        self::assertCount(2, $chats);
        self::assertSame([$fresh->id, $old->id], $chats->pluck('id')->all());
    }

    public function testSkipsInactiveChats(): void
    {
        MaxChat::query()->create([
            'user_id' => 1,
            'chat_id' => 11,
            'status' => MaxChatStatus::Stopped,
        ]);
        MaxChat::query()->create([
            'user_id' => 2,
            'chat_id' => 12,
            'status' => MaxChatStatus::Removed,
        ]);

        self::assertCount(0, (new BroadcastRecipientsResolver())->resolve());
    }

    public function testDeduplicatesByChatId(): void
    {
        $older = MaxChat::query()->create([
            'user_id' => 1,
            'chat_id' => 11,
            'status' => MaxChatStatus::Active,
            'last_activity_at' => Carbon::parse('2026-01-01 10:00:00'),
        ]);
        $newer = MaxChat::query()->create([
            'user_id' => 2,
            'chat_id' => 11,
            'status' => MaxChatStatus::Active,
            'last_activity_at' => Carbon::parse('2026-01-02 10:00:00'),
        ]);

        $chats = (new BroadcastRecipientsResolver())->resolve();

        self::assertCount(1, $chats);
        self::assertSame($newer->id, $chats->first()->id);
        self::assertNotSame($older->id, $chats->first()->id);
    }

    public function testUsesConfiguredChatsModel(): void
    {
        config()->set('filament-max-broadcasts.chats_model', MaxChat::class);

        MaxChat::query()->create([
            'user_id' => 1,
            'chat_id' => 11,
            'status' => MaxChatStatus::Active,
        ]);

        self::assertCount(1, (new BroadcastRecipientsResolver())->resolve());
    }
}
