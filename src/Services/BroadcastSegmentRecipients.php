<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Support\ChatRegistry;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\MaxPhpClient\Enum\ChatType;

/**
 * Получатели сегмента рассылки: только активные диалоги (группы и каналы в сегменты не попадают).
 */
class BroadcastSegmentRecipients
{
    /**
     * @return list<int> активные диалоги сегмента
     */
    public function dialogChatIds(BroadcastSegment $segment): array
    {
        return $this->dialogIdsWithin($segment->chat_ids ?? []);
    }

    /**
     * Число активных чатов сегмента, которые не диалоги (группы, каналы, неизвестный тип).
     */
    public function nonDialogCount(BroadcastSegment $segment): int
    {
        $chatIds = array_values(array_unique(array_map('intval', $segment->chat_ids ?? [])));

        if ($chatIds === []) {
            return 0;
        }

        $chatsModel = ChatRegistry::model();

        return $chatsModel::query()
            ->whereIn('chat_id', $chatIds)
            ->where('status', MaxChatStatus::Active)
            ->where(static function ($query): void {
                $query->where('chat_type', '!=', ChatType::Dialog->value)
                    ->orWhereNull('chat_type');
            })
            ->count();
    }

    /**
     * Число активных диалогов для каждого сегмента одним запросом.
     *
     * @param  iterable<BroadcastSegment>  $segments
     * @return array<int, int> segment id => число получателей
     */
    public function dialogCounts(iterable $segments): array
    {
        $list = [];

        foreach ($segments as $segment) {
            $list[] = $segment;
        }

        $allIds = [];

        foreach ($list as $segment) {
            $allIds = [...$allIds, ...array_map('intval', $segment->chat_ids ?? [])];
        }

        $dialogs = array_flip($this->dialogIdsWithin($allIds));

        $counts = [];

        foreach ($list as $segment) {
            /** @var int $key */
            $key = $segment->getKey();
            $counts[$key] = count(array_filter(
                array_unique(array_map('intval', $segment->chat_ids ?? [])),
                static fn (int $chatId): bool => isset($dialogs[$chatId]),
            ));
        }

        return $counts;
    }

    /**
     * @param  list<int|string>  $chatIds
     * @return list<int>
     */
    private function dialogIdsWithin(array $chatIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $chatIds)));

        if ($ids === []) {
            return [];
        }

        $chatsModel = ChatRegistry::model();

        return array_values(array_map(
            static fn (mixed $chatId): int => (int) (is_scalar($chatId) ? $chatId : 0),
            $chatsModel::query()
                ->whereIn('chat_id', $ids)
                ->where('status', MaxChatStatus::Active)
                ->where('chat_type', ChatType::Dialog->value)
                ->pluck('chat_id')
                ->all(),
        ));
    }
}
