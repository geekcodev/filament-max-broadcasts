<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Database\Eloquent\Collection;

/**
 * Выбор получателей рассылки: все активные чаты из реестра max_chats.
 * Выбор по chat_id, сортировка по last_activity_at (свежие — первыми).
 * Класс вынесен отдельно, чтобы в будущем расширять выбор чатов через
 * config('filament-max-broadcasts.recipients.resolver') без правки хоста.
 */
class BroadcastRecipientsResolver
{
    /**
     * @return Collection<int, MaxChat>
     */
    public function resolve(): Collection
    {
        /** @var class-string<MaxChat> $chatsModel */
        $chatsModel = config('filament-max-broadcasts.chats_model', MaxChat::class);

        return $chatsModel::query()
            ->where('status', MaxChatStatus::Active)
            ->get()
            ->sortByDesc('last_activity_at')
            ->unique('chat_id')
            ->values();
    }
}
