<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\RawChat;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\RawUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\LaravelMaxClient\Models\MaxUser;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

/**
 * Адаптер реестра чатов формы 1.2 (`laravel-max-client` v2): `chatUsers`/`maxUsers`,
 * `title`, `chat_id` как первичный ключ.
 */
class ChatRegistryTest extends TestCase
{
    public function testModelFallsBackToPackageModel(): void
    {
        config()->set('filament-max-broadcasts', Arr::except(
            (array) config('filament-max-broadcasts'),
            'chats_model',
        ));

        self::assertSame(MaxChat::class, ChatRegistry::model());
    }

    public function testModelUsesConfiguredChatsModel(): void
    {
        config()->set('filament-max-broadcasts.chats_model', ConfiguredChat::class);

        self::assertSame(ConfiguredChat::class, ChatRegistry::model());
    }

    public function testUserRelationPointsToChatUsers(): void
    {
        self::assertSame('chatUsers.maxUser', ChatRegistry::userRelation());
        self::assertSame(['chatUsers.maxUser'], ChatRegistry::userRelations());
    }

    public function testWithUsersEagerLoadsChatUsers(): void
    {
        $query = ChatRegistry::withUsers(ChatRegistry::model()::query());
        $eagerLoads = array_keys($query->getEagerLoads());

        self::assertContains('chatUsers', $eagerLoads);
        self::assertContains('chatUsers.maxUser', $eagerLoads);
    }

    public function testWhereUserLikeFindsChatByLastName(): void
    {
        Chats::create(11, 1);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'username' => 'ivan_petrov',
        ]);
        Chats::create(22, 2);
        MaxUser::query()->create([
            'user_id' => 2,
            'first_name' => 'Сидор',
            'last_name' => 'Сидоров',
            'username' => 'sidor',
        ]);

        $found = ChatRegistry::whereUserLike(ChatRegistry::model()::query(), 'Петров')
            ->pluck('chat_id')
            ->all();

        self::assertSame([11], $found);
    }

    public function testWhereUserLikeFindsChatByUsername(): void
    {
        Chats::create(11, 1);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'username' => 'ivan_petrov',
        ]);

        $found = ChatRegistry::whereUserLike(ChatRegistry::model()::query(), 'ivan')
            ->pluck('chat_id')
            ->all();

        self::assertSame([11], $found);
    }

    public function testWhereUserLikeFindsChatByUserNameColumn(): void
    {
        Chats::create(11, 1);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'name' => 'Иван Петров',
        ]);

        $found = ChatRegistry::whereUserLike(ChatRegistry::model()::query(), 'Петров')
            ->pluck('chat_id')
            ->all();

        self::assertContains(11, $found);
    }

    public function testWhereUserLikeKeepsOtherChatsInResult(): void
    {
        Chats::create(11, 1);
        Chats::create(22, 2);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
        ]);

        $found = ChatRegistry::whereUserLike(ChatRegistry::model()::query()->where('chat_id', 11), 'Петров')
            ->pluck('chat_id')
            ->all();

        self::assertSame([11], $found);
    }

    public function testDisplayNamePrefersChatTitle(): void
    {
        $chat = new RawChat(['chat_id' => 11, 'title' => 'Название группы', 'chat_type' => ChatType::Chat]);

        self::assertSame('Название группы', ChatRegistry::displayName($chat));
    }

    public function testDisplayNameUsesDialogUserName(): void
    {
        $chat = Chats::create(11, 1, ['chat_type' => ChatType::Dialog]);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
        ]);

        self::assertSame('Иван Петров', ChatRegistry::displayName($chat->refresh()));
    }

    public function testDisplayNameFallsBackToChatId(): void
    {
        self::assertSame('22', ChatRegistry::displayName(Chats::create(22, 2)));
    }

    public function testChatIdReadsIntegerAttribute(): void
    {
        self::assertSame(7, ChatRegistry::chatId(new RawChat(['chat_id' => 7])));
    }

    public function testChatIdCastsNumericString(): void
    {
        self::assertSame(42, ChatRegistry::chatId(new RawChat(['chat_id' => '42'])));
    }

    public function testChatIdFallsBackToZeroForNonNumericValue(): void
    {
        self::assertSame(0, ChatRegistry::chatId(new RawChat(['chat_id' => 'not-a-number'])));
    }

    public function testUserReturnsNullWithoutRegistryLink(): void
    {
        self::assertNull(ChatRegistry::user(Chats::create(11, 5)));
    }

    public function testUserReturnsLinkedUser(): void
    {
        $chat = Chats::create(11, 1);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
        ]);

        $user = ChatRegistry::user($chat->refresh());

        self::assertNotNull($user);
        self::assertSame('Иван', $user->getAttribute('first_name'));
    }

    public function testUserReturnsNullWhenPivotIsEmpty(): void
    {
        $chat = Chats::create(11, 5);
        $chat->setRelation('chatUsers', new Collection());

        self::assertNull(ChatRegistry::user($chat));
    }

    public function testUserFallsBackToKnownUsersWithoutLoadedPivot(): void
    {
        $chat = Chats::create(11, 1);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
        ]);

        $chat->setRelation('chatUsers', null);

        $user = ChatRegistry::user($chat);

        self::assertNotNull($user);
        self::assertSame(1, $user->getAttribute('user_id'));
    }

    public function testUserIdIsNullForChatWithoutUsers(): void
    {
        $chat = new (ChatRegistry::model())(['chat_id' => 11]);

        self::assertNull(ChatRegistry::userId($chat));
    }

    public function testUserIdReadsPivot(): void
    {
        self::assertSame(1, ChatRegistry::userId(Chats::create(11, 1)));
    }

    public function testUserIdFallsBackToKnownUsersWithoutLoadedPivot(): void
    {
        $chat = Chats::create(11, 1);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
        ]);

        $chat->setRelation('chatUsers', null);

        self::assertSame(1, ChatRegistry::userId($chat));
    }

    public function testUserIdIsNullWithoutKnownUsers(): void
    {
        $chat = new (ChatRegistry::model())(['chat_id' => 11]);
        $chat->setRelation('chatUsers', null);

        self::assertNull(ChatRegistry::userId($chat));
    }

    public function testChatTypeReturnsEnumValue(): void
    {
        self::assertSame('channel', ChatRegistry::chatType(new RawChat(['chat_type' => ChatType::Channel])));
    }

    public function testChatTypeReturnsRawString(): void
    {
        self::assertSame('supergroup', ChatRegistry::chatType(new RawChat(['chat_type' => 'supergroup'])));
    }

    public function testChatTypeFallsBackToUnknown(): void
    {
        self::assertSame('unknown', ChatRegistry::chatType(new RawChat(['chat_type' => ''])));
        self::assertSame('unknown', ChatRegistry::chatType(new RawChat()));
    }

    public function testUserNamePrefersFullName(): void
    {
        self::assertSame(
            'Иван Петров',
            ChatRegistry::userName(new RawUser(['name' => 'Иван Петров', 'first_name' => 'Иван'])),
        );
    }

    public function testUserNameJoinsFirstAndLastName(): void
    {
        self::assertSame(
            'Иван Петров',
            ChatRegistry::userName(new RawUser(['first_name' => 'Иван', 'last_name' => 'Петров'])),
        );
    }

    public function testUserNameJoinsFirstNameWithoutLastName(): void
    {
        self::assertSame('Иван', ChatRegistry::userName(new RawUser(['first_name' => 'Иван'])));
    }

    public function testUserNameFallsBackToUsername(): void
    {
        self::assertSame('ivan', ChatRegistry::userName(new RawUser(['username' => 'ivan'])));
    }

    public function testUserNameIsEmptyWithoutAnyName(): void
    {
        self::assertSame('', ChatRegistry::userName(new RawUser()));
        self::assertSame('', ChatRegistry::userName(null));
    }

    public function testUserResolversTolerateModelsWithoutRegistryRelations(): void
    {
        $chat = new class () extends \Illuminate\Database\Eloquent\Model {
        };

        self::assertNull(ChatRegistry::userId($chat));
        self::assertNull(ChatRegistry::user($chat));
    }
}

/**
 * Модель чата, переопределённая хостом через `chats_model`.
 *
 * Наследует `MaxChat`: конфигурация хоста обязана оставаться подклассом модели
 * ядра, это и проверяет тест.
 */
final class ConfiguredChat extends MaxChat
{
    protected $table = 'max_chats';
}
