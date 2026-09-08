<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Support\ChatSelectionField;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\LaravelMaxClient\Models\MaxUser;
use GeekCo\MaxPhpClient\Enum\ChatType;

class ChatSelectionFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ru');
    }

    public function testOptionsReturnsRecentActiveChatsWithoutDuplicates(): void
    {
        MaxChat::query()->create([
            'user_id' => 1,
            'chat_id' => 11,
            'status' => MaxChatStatus::Active,
            'last_activity_at' => now()->subMinutes(5),
        ]);
        MaxChat::query()->create([
            'user_id' => 2,
            'chat_id' => 22,
            'status' => MaxChatStatus::Active,
            'last_activity_at' => now(),
        ]);
        MaxChat::query()->create(['user_id' => 3, 'chat_id' => 33, 'status' => MaxChatStatus::Stopped]);
        MaxChat::query()->create(['user_id' => 4, 'chat_id' => 11, 'status' => MaxChatStatus::Active]);

        $options = ChatSelectionField::options();

        self::assertSame([22, 11], array_keys($options));
    }

    public function testSearchResultsMatchesChatIdAndName(): void
    {
        MaxChat::query()->create(['user_id' => 1, 'chat_id' => 11, 'status' => MaxChatStatus::Active]);
        MaxChat::query()->create(['user_id' => 2, 'chat_id' => 22, 'status' => MaxChatStatus::Active]);
        MaxUser::query()->create([
            'user_id' => 2,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'username' => 'ivan_petrov',
        ]);
        MaxChat::query()->create(['user_id' => 3, 'chat_id' => 33, 'status' => MaxChatStatus::Stopped]);

        self::assertSame([11], array_keys(ChatSelectionField::searchResults('11')));
        self::assertSame([22], array_keys(ChatSelectionField::searchResults('Иван')));
        self::assertSame([], ChatSelectionField::searchResults('33'));
        self::assertSame([], ChatSelectionField::searchResults(''));
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

        self::assertStringContainsString('(ID: 11)', $labels[11]);
        self::assertSame('999', $labels[999]);
    }

    public function testOptionLabelsEscapesRawFallbackValue(): void
    {
        $labels = ChatSelectionField::optionLabels(['<script>alert(1)</script>']);

        self::assertSame(
            ['<script>alert(1)</script>' => '&lt;script&gt;alert(1)&lt;/script&gt;'],
            $labels,
        );
    }

    public function testOptionLabelsHandlesEmptySelection(): void
    {
        self::assertSame([], ChatSelectionField::optionLabels([]));
    }

    public function testLabelForDialogShowsTypeBadgeAndUserName(): void
    {
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'username' => 'ivan_petrov',
            'name' => 'Иван Петров',
        ]);
        $chat = MaxChat::query()->create([
            'user_id' => 1,
            'chat_id' => 11,
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Dialog,
        ]);

        $label = ChatSelectionField::labelFor($chat);

        self::assertStringContainsString('fi-badge', $label);
        self::assertStringContainsString('Диалог', $label);
        self::assertStringContainsString('Иван Петров', $label);
        self::assertStringContainsString('(ID: 11)', $label);
        self::assertStringNotContainsString('ivan_petrov', $label);
    }

    public function testLabelFallsBackToChatIdForGroupWithoutTitle(): void
    {
        $chat = MaxChat::query()->create([
            'user_id' => 2,
            'chat_id' => 22,
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Chat,
        ]);

        $label = ChatSelectionField::labelFor($chat);

        self::assertStringContainsString('Группа', $label);
        self::assertStringContainsString('(ID: 22)', $label);
        self::assertStringContainsString('var(--info-50)', $label);
    }

    public function testLabelFallsBackToUnknownType(): void
    {
        StringChatTypeChat::query()->create([
            'user_id' => 3,
            'chat_id' => 33,
            'status' => MaxChatStatus::Active,
            'chat_type' => 'supergroup',
        ]);

        $chat = StringChatTypeChat::query()->where('chat_id', 33)->firstOrFail();

        $label = ChatSelectionField::labelFor($chat);

        self::assertStringContainsString('Чат', $label);
        self::assertStringContainsString('var(--gray-50)', $label);
        self::assertStringNotContainsString('supergroup', $label);
    }
}

final class StringChatTypeChat extends MaxChat
{
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'chat_id' => 'integer',
            'status' => MaxChatStatus::class,
            'chat_type' => 'string',
            'last_activity_at' => 'datetime',
        ];
    }
}
