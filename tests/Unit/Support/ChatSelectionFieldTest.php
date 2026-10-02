<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Support\ChatSelectionField;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\RawChat;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
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
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
            'last_activity_at' => now()->subMinutes(5),
        ]);
        Chats::create(22, 2, [
            'status' => MaxChatStatus::Active,
            'last_activity_at' => now(),
        ]);
        Chats::create(33, 3, [
            'status' => MaxChatStatus::Stopped,
        ]);
        Chats::create(11, 4, [
            'status' => MaxChatStatus::Active,
        ]);

        $options = ChatSelectionField::options();

        self::assertSame([22, 11], array_keys($options));
    }

    public function testSearchResultsMatchesChatIdAndName(): void
    {
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
        ]);
        Chats::create(22, 2, [
            'status' => MaxChatStatus::Active,
        ]);
        MaxUser::query()->create([
            'user_id' => 2,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'username' => 'ivan_petrov',
        ]);
        Chats::create(33, 3, [
            'status' => MaxChatStatus::Stopped,
        ]);

        self::assertSame([11], array_keys(ChatSelectionField::searchResults('11')));
        self::assertSame([22], array_keys(ChatSelectionField::searchResults('Иван')));
        self::assertSame([], ChatSelectionField::searchResults('33'));
        self::assertSame([], ChatSelectionField::searchResults(''));
    }

    public function testSearchResultsDeduplicatesByChatId(): void
    {
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
        ]);
        Chats::addUser(11, 2);

        self::assertSame([11], array_keys(ChatSelectionField::searchResults('11')));
    }

    public function testOptionLabelsResolveActiveChatsAndFallBackToRawValue(): void
    {
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
        ]);

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
        $chat = Chats::create(11, 1, [
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
        $chat = Chats::create(22, 2, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Chat,
        ]);

        $label = ChatSelectionField::labelFor($chat);

        self::assertStringContainsString('Группа', $label);
        self::assertStringContainsString('(ID: 22)', $label);
        self::assertStringContainsString('var(--info-50)', $label);
    }

    public function testLabelForChannelUsesWarningBadge(): void
    {
        // `title` есть только в реестре 1.2, поэтому атрибут задаётся напрямую.
        $chat = new RawChat(['chat_id' => 44, 'chat_type' => ChatType::Channel, 'title' => 'Канал новостей']);

        $label = ChatSelectionField::labelFor($chat);

        self::assertStringContainsString('Канал', $label);
        self::assertStringContainsString('var(--warning-50)', $label);
        self::assertStringContainsString('Канал новостей', $label);
        self::assertStringContainsString('(ID: 44)', $label);
    }

    public function testLabelUsesChatTitleForGroup(): void
    {
        $chat = new RawChat(['chat_id' => 55, 'chat_type' => ChatType::Chat, 'title' => 'Название группы']);

        self::assertStringContainsString('Название группы', ChatSelectionField::labelFor($chat));
    }

    public function testLabelFallsBackToUnknownType(): void
    {
        $chat = new RawChat(['chat_id' => 33, 'chat_type' => 'supergroup']);

        $label = ChatSelectionField::labelFor($chat);

        self::assertStringContainsString('Чат', $label);
        self::assertStringContainsString('var(--gray-50)', $label);
        self::assertStringNotContainsString('supergroup', $label);
    }

    public function testLabelFallsBackToZeroForNonNumericChatId(): void
    {
        $chat = new RawChat(['chat_id' => 'not-a-number']);

        self::assertStringContainsString('(ID: 0)', ChatSelectionField::labelFor($chat));
    }

    public function testLabelCastsNumericStringChatId(): void
    {
        $chat = new RawChat(['chat_id' => '77']);

        self::assertStringContainsString('(ID: 77)', ChatSelectionField::labelFor($chat));
    }

    public function testChatTypeLabelCoversAllKnownTypes(): void
    {
        self::assertSame('Диалог', ChatSelectionField::chatTypeLabel(new RawChat(['chat_type' => ChatType::Dialog])));
        self::assertSame('Группа', ChatSelectionField::chatTypeLabel(new RawChat(['chat_type' => ChatType::Chat])));
        self::assertSame('Канал', ChatSelectionField::chatTypeLabel(new RawChat(['chat_type' => ChatType::Channel])));
        self::assertSame('Чат', ChatSelectionField::chatTypeLabel(new RawChat()));
    }

    public function testChatTypeColorCoversAllKnownTypes(): void
    {
        self::assertSame('success', ChatSelectionField::chatTypeColor(new RawChat(['chat_type' => ChatType::Dialog])));
        self::assertSame('warning', ChatSelectionField::chatTypeColor(new RawChat(['chat_type' => ChatType::Channel])));
        self::assertSame('gray', ChatSelectionField::chatTypeColor(new RawChat()));
    }

    public function testDisplayNameDelegatesToRegistry(): void
    {
        $chat = new RawChat(['chat_id' => 66, 'chat_type' => ChatType::Chat, 'title' => 'Название для проверки']);

        self::assertSame('Название для проверки', ChatSelectionField::displayName($chat));
    }

    public function testOptionLabelsSkipsNonScalarValues(): void
    {
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
        ]);

        $labels = ChatSelectionField::optionLabels([11, ['nested'], new \stdClass()]);

        self::assertArrayHasKey(11, $labels);
        self::assertCount(1, $labels);
    }

    public function testOptionLabelsIsEmptyWhenSelectionHasOnlyNonScalarValues(): void
    {
        self::assertSame([], ChatSelectionField::optionLabels([['nested'], new \stdClass()]));
    }

    public function testMakeConfiguresMultipleSearchableSelect(): void
    {
        $select = ChatSelectionField::make('recipient_chat_ids', 'Получатели', helperText: 'Подсказка', required: true);

        self::assertTrue($select->isMultiple());
        self::assertTrue($select->isSearchable());
        self::assertTrue($select->isRequired());
        self::assertSame('recipient_chat_ids', $select->getName());
        self::assertSame('Получатели', $select->getLabel());
    }
}
