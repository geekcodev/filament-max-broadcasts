<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Support\ChatSelectionField;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;

class ChatSelectionFieldTest extends TestCase
{
    public function testSearchResultsReturnsMatchingActiveChats(): void
    {
        MaxChat::query()->create(['user_id' => 1, 'chat_id' => 11, 'status' => MaxChatStatus::Active]);
        MaxChat::query()->create(['user_id' => 2, 'chat_id' => 22, 'status' => MaxChatStatus::Active]);
        MaxChat::query()->create(['user_id' => 3, 'chat_id' => 33, 'status' => MaxChatStatus::Stopped]);

        self::assertSame([11], array_keys(ChatSelectionField::searchResults('11')));
        self::assertSame([22], array_keys(ChatSelectionField::searchResults('22')));
        self::assertSame([], ChatSelectionField::searchResults('33'));
        self::assertSame([], ChatSelectionField::searchResults('absent'));
    }

    public function testSearchResultsDeduplicatesByChatId(): void
    {
        MaxChat::query()->create(['user_id' => 1, 'chat_id' => 11, 'status' => MaxChatStatus::Active]);
        MaxChat::query()->create(['user_id' => 2, 'chat_id' => 11, 'status' => MaxChatStatus::Active]);

        self::assertSame([11], array_keys(ChatSelectionField::searchResults('11')));
    }

    public function testOptionLabelsResolveActiveChatsAndFallBackToRawValue(): void
    {
        MaxChat::query()->create(['user_id' => 1, 'chat_id' => 11, 'status' => MaxChatStatus::Active]);

        $labels = ChatSelectionField::optionLabels([11, 999]);

        self::assertSame('11 (ID: 11)', $labels[11]);
        self::assertSame('999', $labels[999]);
    }

    public function testOptionLabelsHandlesEmptySelection(): void
    {
        self::assertSame([], ChatSelectionField::optionLabels([]));
    }
}
