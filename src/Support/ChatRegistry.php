<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Адаптер к реестру чатов `laravel-max-client`: единственное место, которое знает
 * про обе формы реестра (gotcha 1).
 *
 * 1.1.x — строка на пару (пользователь, чат): у чата колонка `user_id` и связь
 * `maxUser`, ключ суррогатный `id`. 1.2 — строка на чат: `chat_id` это
 * первичный ключ, связи чат-пользователь живут в `max_chat_users` (`chatUsers`,
 * `maxUsers`), а у чата появляется `title` из `getChat()`.
 *
 * Форма определяется по установленной версии ядра, поэтому один и тот же код
 * работает и на 1.1.x, и на 1.2, а дёшево проверяется тестами на обеих формах.
 */
final class ChatRegistry
{
    /**
     * @return class-string<MaxChat>
     */
    public static function model(): string
    {
        /** @var class-string<MaxChat> $model */
        $model = config('filament-max-broadcasts.chats_model', MaxChat::class);

        return $model;
    }

    /**
     * Связи чат-пользователь, доступные на установленной форме реестра.
     *
     * @return list<string>
     */
    public static function userRelations(): array
    {
        return [self::userRelation()];
    }

    public static function userRelation(): string
    {
        return method_exists(self::chatInstance(), 'maxUser') ? 'maxUser' : 'chatUsers.maxUser';
    }

    /**
     * Подгружает связи чат-пользователь вместе с чатами.
     *
     * @param  Builder<MaxChat>  $query
     * @return Builder<MaxChat>
     */
    public static function withUsers(Builder $query): Builder
    {
        return $query->with(self::userRelation());
    }

    /**
     * Добавляет к выборке чатов условие поиска по имени пользователя чата.
     *
     * @param  Builder<MaxChat>  $query
     * @return Builder<MaxChat>
     */
    public static function whereUserLike(Builder $query, string $search): Builder
    {
        return $query->orWhereHas(self::userRelation(), static function (Builder $userQuery) use ($search): void {
            $userQuery
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%");
        });
    }

    /**
     * Имя чата для интерфейса: `title` группы или канала (1.2), иначе имя
     * собеседника диалога, иначе идентификатор чата.
     */
    public static function displayName(Model $chat): string
    {
        $title = self::stringAttr($chat, 'title');

        if ($title !== '') {
            return $title;
        }

        if (self::chatType($chat) === ChatType::Dialog->value) {
            $name = self::userName(self::user($chat));

            if ($name !== '') {
                return $name;
            }
        }

        return (string) self::chatId($chat);
    }

    public static function chatId(Model $chat): int
    {
        $chatId = $chat->getAttribute('chat_id');

        if (is_int($chatId)) {
            return $chatId;
        }

        if (is_string($chatId) && is_numeric($chatId)) {
            return (int) $chatId;
        }

        return 0;
    }

    /**
     * Пользователь, зафиксированный в реестре для чата.
     *
     * @return Model|null пользователь MAX (в 1.1 — `maxUser`, в 1.2 — первый известный участник чата)
     */
    public static function user(Model $chat): ?Model
    {
        if (method_exists(self::chatInstance(), 'maxUser')) {
            $user = $chat->getRelationValue('maxUser');

            return $user instanceof Model ? $user : null;
        }

        $chatUsers = $chat->getRelationValue('chatUsers');

        if ($chatUsers instanceof Collection) {
            $first = $chatUsers->first();
            $user = $first instanceof Model ? $first->getRelationValue('maxUser') : null;

            return $user instanceof Model ? $user : null;
        }

        $userId = self::userId($chat);

        if ($userId === null) {
            return null;
        }

        return self::firstKnownUser($chat);
    }

    /**
     * Идентификатор пользователя чата из реестра.
     *
     * На форме 1.2 колонки `user_id` у чата нет, поэтому берётся первый известный
     * участник (`max_chat_users`). Значение нужно только для снимка получателя:
     * отправка идёт по `chat_id`.
     */
    public static function userId(Model $chat): ?int
    {
        $userId = $chat->getAttribute('user_id');

        if (is_int($userId)) {
            return $userId;
        }

        if (is_string($userId) && is_numeric($userId)) {
            return (int) $userId;
        }

        if (! method_exists(self::chatInstance(), 'chatUsers')) {
            return null;
        }

        $chatUsers = $chat->getRelationValue('chatUsers');

        if ($chatUsers instanceof Collection) {
            $first = $chatUsers->first();
            $userId = $first instanceof Model ? $first->getAttribute('user_id') : null;

            return is_int($userId) ? $userId : null;
        }

        return self::firstKnownUserId($chat);
    }

    /**
     * Первый пользователь чата по связи `maxUsers` (только форма 1.2).
     *
     * Имя связи вызывается динамически: на форме 1.1 такого метода у модели нет,
     * и PHPStan не должен ругаться на отсутствующий метод (gotcha 16).
     */
    private static function firstKnownUser(Model $chat): ?Model
    {
        $user = self::knownUsers($chat)?->first();

        return $user instanceof Model ? $user : null;
    }

    private static function firstKnownUserId(Model $chat): ?int
    {
        $user = self::knownUsers($chat)?->first();
        $userId = $user instanceof Model ? $user->getAttribute('user_id') : null;

        return is_int($userId) ? $userId : null;
    }

    /**
     * @return Collection<int, Model>|null
     */
    private static function knownUsers(Model $chat): ?Collection
    {
        $method = 'maxUsers';

        if (! method_exists($chat, $method)) {
            return null;
        }

        /** @var Relation<Model, Model, int> $relation */
        $relation = $chat->{$method}();

        return $relation->get();
    }

    /**
     * Пустой экземпляр модели чата для проверки формы реестра.
     *
     * Возвращается как `Model`, а не как `MaxChat`: у ядра две несовместимые формы
     * реестра, поэтому проверять наличие связей нужно динамически, иначе статический
     * анализ считает проверку избыточной на одной из форм (gotcha 1).
     */
    private static function chatInstance(): Model
    {
        $class = self::model();

        /** @var Model $chat */
        $chat = new $class();

        return $chat;
    }

    public static function chatType(Model $chat): string
    {
        $type = $chat->getAttribute('chat_type');

        if ($type instanceof ChatType) {
            return $type->value;
        }

        if (is_string($type) && $type !== '') {
            return $type;
        }

        return 'unknown';
    }

    public static function userName(?Model $user): string
    {
        if (! $user instanceof Model) {
            return '';
        }

        $name = self::stringAttr($user, 'name');

        if ($name === '') {
            $name = trim(implode(' ', array_filter(
                [self::stringAttr($user, 'first_name'), self::stringAttr($user, 'last_name')],
                static fn (string $value): bool => $value !== '',
            )));
        }

        if ($name === '') {
            return self::stringAttr($user, 'username');
        }

        return $name;
    }

    private static function stringAttr(Model $model, string $key): string
    {
        $value = $model->getAttribute($key);

        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
