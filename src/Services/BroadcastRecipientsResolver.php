<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
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

    /**
     * Чаты из заданного списка chat_id: только активные, без дедупликации и с сортировкой.
     *
     * Штатный резолвер читает из реестра только запрошенные чаты. Подкласс, который
     * переопределяет resolve(), по умолчанию получает безопасный путь через свой resolve():
     * его правила не теряются, но выборка остаётся полной. Если подкласс не меняет правила,
     * он может переопределить supportsScopedResolution() и получить быстрый путь.
     *
     * @param  list<int>  $chatIds
     * @return Collection<int, MaxChat>
     */
    public function resolveForChatIds(array $chatIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $chatIds)));

        if ($ids === []) {
            return new Collection();
        }

        if ($this->supportsScopedResolution()) {
            return $this->queryByIds($ids);
        }

        $wanted = array_fill_keys($ids, true);

        return $this->resolve()
            ->filter(static function (MaxChat $chat) use ($wanted): bool {
                /** @var int $chatId */
                $chatId = $chat->getAttribute('chat_id');

                return isset($wanted[$chatId]);
            })
            ->values();
    }

    /**
     * Можно ли читать только запрошенные chat_id, не теряя правил хоста.
     */
    protected function supportsScopedResolution(): bool
    {
        return static::class === self::class;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, MaxChat>
     */
    private function queryByIds(array $ids): Collection
    {
        /** @var class-string<MaxChat> $chatsModel */
        $chatsModel = config('filament-max-broadcasts.chats_model', MaxChat::class);

        $chats = new Collection();

        foreach (array_chunk($ids, 500) as $chunk) {
            $chats = $chats->merge(
                ChatRegistry::withUsers(
                    $chatsModel::query()
                        ->whereIn('chat_id', $chunk)
                        ->where('status', MaxChatStatus::Active),
                )->get(),
            );
        }

        return $chats
            ->unique('chat_id')
            ->sortByDesc('last_activity_at')
            ->values();
    }
}
