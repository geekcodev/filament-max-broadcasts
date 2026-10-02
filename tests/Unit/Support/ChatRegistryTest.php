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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * Адаптер реестра чатов (gotcha 1): тесты описывают поведение одинаково для обеих
 * форм реестра, а форму выбирает установленная версия `laravel-max-client`. Стабы
 * ниже нужны для веток, которые на установленной форме недостижимы (например,
 * колонка `user_id` есть только в 1.1.x).
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

    public function testUserRelationMatchesInstalledRegistryForm(): void
    {
        $relation = ChatRegistry::userRelation();

        self::assertContains($relation, ['maxUser', 'chatUsers.maxUser']);
        self::assertSame([$relation], ChatRegistry::userRelations());

        // Выбранная форма должна реально существовать в установленной модели
        // реестра: на 1.1 связь `maxUser` лежит на самом чате, на 1.2 — в pivot.
        $root = explode('.', $relation)[0];

        self::assertTrue(method_exists(ChatRegistry::model(), $root));
    }

    public function testUserRelationReportsMaxUserOnLegacyChatWithUserColumn(): void
    {
        config()->set('filament-max-broadcasts.chats_model', LegacyChat::class);

        self::assertSame('maxUser', ChatRegistry::userRelation());
    }

    public function testUserRelationRootIsDeclaredOnInstalledModel(): void
    {
        config()->set('filament-max-broadcasts.chats_model', PivotChat::class);

        $root = explode('.', ChatRegistry::userRelation())[0];

        self::assertTrue(method_exists(new PivotChat(), $root));
    }

    public function testWithUsersEagerLoadsRelationOfInstalledForm(): void
    {
        $query = ChatRegistry::withUsers(ChatRegistry::model()::query());

        self::assertContains(ChatRegistry::userRelation(), array_keys($query->getEagerLoads()));
    }

    public function testWithUsersEagerLoadsMaxUserOnLegacyForm(): void
    {
        config()->set('filament-max-broadcasts.chats_model', LegacyChat::class);

        /** @var Builder<MaxChat> $legacyQuery */
        $legacyQuery = LegacyChat::query();
        $query = ChatRegistry::withUsers($legacyQuery);

        self::assertSame(['maxUser'], array_keys($query->getEagerLoads()));
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
        // `title` есть только в реестре 1.2, поэтому атрибут задаётся напрямую:
        // проверка формы должна работать на обеих версиях ядра (gotcha 1).
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
        // Пользователь в реестре есть, записи в `max_users` нет: связь не
        // разрешается ни через `maxUser`, ни через pivot чат-пользователь.
        self::assertNull(ChatRegistry::user(Chats::create(11, 5)));
    }

    public function testUserReturnsLinkedUserOfInstalledForm(): void
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

    public function testUserReadsLegacyMaxUserRelation(): void
    {
        config()->set('filament-max-broadcasts.chats_model', LegacyChat::class);

        $chat = new LegacyChat(['chat_id' => 11, 'user_id' => 5]);
        $user = new RawUser(['user_id' => 5, 'first_name' => 'Иван']);
        $chat->setRelation('maxUser', $user);

        self::assertSame($user, ChatRegistry::user($chat));
    }

    public function testUserReturnsNullWhenLegacyRelationIsAbsent(): void
    {
        config()->set('filament-max-broadcasts.chats_model', LegacyChat::class);

        self::assertNull(ChatRegistry::user(new LegacyChat(['chat_id' => 11, 'user_id' => 5])));
    }

    public function testUserFallsBackToUserIdWithoutLoadedPivot(): void
    {
        // Связь чат-пользователь на форме 1.2 лежит в `max_chat_users`; если она не
        // подгружена, адаптер берёт первого известного пользователя по `maxUsers`.
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

    public function testUserIdReadsUserColumnOfLegacyForm(): void
    {
        config()->set('filament-max-broadcasts.chats_model', LegacyChat::class);

        self::assertSame(5, ChatRegistry::userId((new LegacyChat())->forceFill(['chat_id' => 11, 'user_id' => 5])));
    }

    public function testUserIdCastsNumericString(): void
    {
        self::assertSame(5, ChatRegistry::userId((new RawChat())->forceFill(['user_id' => '5'])));
    }

    public function testUserIdIsNullWithoutUserColumnMethod(): void
    {
        config()->set('filament-max-broadcasts.chats_model', PivotlessChat::class);

        self::assertNull(ChatRegistry::userId(new PivotlessChat(['chat_id' => 11])));
    }

    public function testUserIdReadsPivotOfInstalledForm(): void
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
}

/**
 * Форма реестра 1.1.x: колонка `user_id` в строке чата и связь `maxUser`.
 *
 * Стаб намеренно не наследует `MaxChat`: на форме 1.1 связь `maxUser` уже
 * объявлена ядром, и перекрытие её в подклассе конфликтует с дженериком
 * родителя в PHPStan level max. Здесь важна только форма — колонка `user_id` и
 * связь с пользователем, — поэтому достаточно обычной модели.
 */
final class LegacyChat extends Model
{
    protected $table = 'max_chats';

    protected $guarded = [];

    public $timestamps = true;

    /**
     * @return BelongsTo<MaxUser, $this>
     */
    public function maxUser(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'user_id', 'user_id');
    }
}

/**
 * Форма реестра 1.2: связь `maxUser` на чате отсутствует, участники чата лежат
 * в pivot `max_chat_users` (`chatUsers`).
 *
 * Как и `LegacyChat`, стаб не наследует `MaxChat`: на форме 1.1 ядро объявляет
 * `maxUser` у модели чата, а перекрытие связи в подклассе конфликтует с
 * дженериком родителя в PHPStan level max.
 */
final class PivotChat extends Model
{
    protected $table = 'max_chats';

    public $timestamps = true;

    /**
     * @return HasMany<PivotUser, $this>
     */
    public function chatUsers(): HasMany
    {
        return $this->hasMany(PivotUser::class, 'chat_id', 'chat_id');
    }
}

/**
 * Строка pivot `max_chat_users`. Отдельный стаб, а не `MaxChatUser` ядра: на форме
 * 1.1 такого класса в пакете нет, а проверять форму нужно на обеих версиях.
 */
final class PivotUser extends Model
{
    protected $table = 'max_chat_users';

    public $timestamps = true;

    /**
     * @return BelongsTo<MaxUser, $this>
     */
    public function maxUser(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'user_id', 'user_id');
    }
}

/**
 * Форма реестра без колонки `user_id` и без pivot: идентификатор пользователя
 * недоступен, и снимок получателя остаётся с пустым `user_id`.
 */
final class PivotlessChat extends Model
{
    protected $table = 'max_chats';

    protected $guarded = [];

    public $timestamps = true;
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
