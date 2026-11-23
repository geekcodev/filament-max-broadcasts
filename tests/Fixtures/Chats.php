<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Fixtures;

use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Фикстура чатов реестра `laravel-max-client` формы 1.2: строка на чат, участники —
 * в `max_chat_users`.
 *
 * Строка в `max_chat_users` пишется query builder'ом, а не моделью `MaxChatUser`:
 * так фикстура не зависит от состава полей модели ядра.
 */
final class Chats
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(int $chatId, ?int $userId = null, array $attributes = []): MaxChat
    {
        /** @var MaxChat|null $existing */
        $existing = MaxChat::query()->find($chatId);

        if ($existing instanceof MaxChat) {
            if ($userId !== null) {
                self::addUser($chatId, $userId);
            }

            return $existing;
        }

        /** @var MaxChat $chat */
        $chat = MaxChat::query()->create(array_merge([
            'chat_id' => $chatId,
            'status' => MaxChatStatus::Active,
        ], $attributes));

        if ($userId !== null) {
            self::addUser($chatId, $userId);
        }

        return $chat;
    }

    /**
     * Ещё один пользователь того же чата.
     */
    public static function addUser(int $chatId, int $userId): void
    {
        DB::table('max_chat_users')->insert([
            'id' => (string) Str::uuid(),
            'chat_id' => $chatId,
            'user_id' => $userId,
            'status' => MaxChatStatus::Active->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
