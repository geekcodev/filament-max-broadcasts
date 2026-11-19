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
 * Адаптер к реестру чатов `laravel-max-client` формы 1.2.
 *
 * Строка реестра — один чат (`chat_id` — первичный ключ), участники чата лежат в
 * `max_chat_users` (`chatUsers`, `maxUsers`), у группы и канала есть `title`.
 * Всё общение пакета с реестром идёт через этот класс: имена связей и способ
 * получить пользователя чата не должны расползаться по коду.
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
     * @return list<string>
     */
    public static function userRelations(): array
    {
        return [self::userRelation()];
    }

    public static function userRelation(): string
    {
        return 'chatUsers.maxUser';
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
     * Имя чата для интерфейса: `title` группы или канала, иначе имя собеседника
     * диалога, иначе идентификатор чата.
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
     * Пользователь, зафиксированный в реестре для чата (первый известный участник).
     */
    public static function user(Model $chat): ?Model
    {
        $chatUsers = $chat->getRelationValue('chatUsers');

        if ($chatUsers instanceof Collection) {
            $first = $chatUsers->first();
            $user = $first instanceof Model ? $first->getRelationValue('maxUser') : null;

            return $user instanceof Model ? $user : null;
        }

        $user = self::knownUsers($chat)?->first();

        return $user instanceof Model ? $user : null;
    }

    /**
     * Идентификатор пользователя чата из реестра (первый известный участник).
     * Значение нужно только для снимка получателя: отправка идёт по `chat_id`.
     */
    public static function userId(Model $chat): ?int
    {
        $chatUsers = $chat->getRelationValue('chatUsers');

        if ($chatUsers instanceof Collection) {
            $first = $chatUsers->first();
            $userId = $first instanceof Model ? $first->getAttribute('user_id') : null;

            return is_int($userId) ? $userId : null;
        }

        $user = self::knownUsers($chat)?->first();
        $userId = $user instanceof Model ? $user->getAttribute('user_id') : null;

        return is_int($userId) ? $userId : null;
    }

    /**
     * @return Collection<int, Model>|null
     */
    private static function knownUsers(Model $chat): ?Collection
    {
        if (! method_exists($chat, 'maxUsers')) {
            return null;
        }

        /** @var Relation<Model, Model, int> $relation */
        $relation = $chat->maxUsers();

        return $relation->get();
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
