<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Fixtures;

use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Фикстура чатов реестра `laravel-max-client`, одинаковая для обеих форм (gotcha 1).
 *
 * Форма 1.1.x хранит пользователя колонкой `user_id` в строке чата, поэтому «тот
 * же чат, другой пользователь» — это вторая строка реестра. Форма 1.2 хранит
 * строку на чат, а пользователя — в `max_chat_users`. Набор тестов один и тот же,
 * различается только способ связать чат с пользователем.
 *
 * Строка в `max_chat_users` пишетсяquery builder'ом, а не моделью `MaxChatUser`:
 * на форме 1.1.x такого класса в пакете нет, и фикстура должна собираться с обеими
 * версиями ядра.
 */
final class Chats
{
    private static ?bool $hasUserColumn = null;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(int $chatId, ?int $userId = null, array $attributes = []): MaxChat
    {
        if (! self::hasUserColumn()) {
            /** @var MaxChat|null $existing */
            $existing = MaxChat::query()->find($chatId);

            if ($existing instanceof MaxChat) {
                if ($userId !== null) {
                    self::addUser($chatId, $userId);
                }

                return $existing;
            }
        }

        $payload = array_merge([
            'chat_id' => $chatId,
            'status' => MaxChatStatus::Active,
        ], $attributes);

        if (self::hasUserColumn()) {
            $payload['user_id'] = $userId;
        }

        /** @var MaxChat $chat */
        $chat = MaxChat::query()->create($payload);

        if ($userId !== null && ! self::hasUserColumn()) {
            self::addUser($chatId, $userId);
        }

        return $chat;
    }

    /**
     * Ещё один пользователь того же чата: строка реестра на форме 1.1.x, запись в
     * `max_chat_users` на форме 1.2.
     */
    public static function addUser(int $chatId, int $userId): void
    {
        if (self::hasUserColumn()) {
            MaxChat::query()->create([
                'user_id' => $userId,
                'chat_id' => $chatId,
                'status' => MaxChatStatus::Active,
            ]);

            return;
        }

        DB::table('max_chat_users')->insert([
            'id' => (string) Str::uuid(),
            'chat_id' => $chatId,
            'user_id' => $userId,
            'status' => MaxChatStatus::Active->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Форма реестра: 1.1.x — колонка `user_id`, 1.2 — `max_chat_users`.
     */
    public static function hasUserColumn(): bool
    {
        return self::$hasUserColumn ??= Schema::hasColumn('max_chats', 'user_id');
    }
}
