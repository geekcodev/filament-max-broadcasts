<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Support\Carbon;

class BroadcastRecipientsResolverTest extends TestCase
{
    public function testResolvesActiveChatsSortedByLastActivity(): void
    {
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
            'last_activity_at' => Carbon::parse('2026-01-01 10:00:00'),
        ]);
        Chats::create(12, 2, [
            'status' => MaxChatStatus::Active,
            'last_activity_at' => Carbon::parse('2026-01-02 10:00:00'),
        ]);

        $chats = (new BroadcastRecipientsResolver())->resolve();

        self::assertCount(2, $chats);
        self::assertSame([12, 11], $chats->pluck('chat_id')->all());
    }

    public function testSkipsInactiveChats(): void
    {
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Stopped,
        ]);
        Chats::create(12, 2, [
            'status' => MaxChatStatus::Removed,
        ]);

        self::assertCount(0, (new BroadcastRecipientsResolver())->resolve());
    }

    public function testDeduplicatesByChatId(): void
    {
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
            'last_activity_at' => Carbon::parse('2026-01-01 10:00:00'),
        ]);
        Chats::addUser(11, 2);

        $chats = (new BroadcastRecipientsResolver())->resolve();

        self::assertCount(1, $chats);
        self::assertSame([11], $chats->pluck('chat_id')->all());
    }

    public function testUsesConfiguredChatsModel(): void
    {
        config()->set('filament-max-broadcasts.chats_model', MaxChat::class);

        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
        ]);

        self::assertCount(1, (new BroadcastRecipientsResolver())->resolve());
    }
}
