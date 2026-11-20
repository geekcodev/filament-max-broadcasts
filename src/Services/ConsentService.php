<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;

/**
 * Единая точка изменения согласий и записи фактов opt-in/opt-out.
 *
 * Согласие — это факт по паре (сегмент, чат) в max_broadcast_consents. Состав сегмента
 * (chat_ids) задаётся вручную и ответами не меняется: сегмент — это аудитория, которую
 * опрашивают, а рассылка по сегменту уходит только тем, кто ответил opt_in. Поэтому
 * список согласившихся вычисляется из таблицы согласий, а не хранится в самом сегменте.
 *
 * Запись pending означает только отправленный запрос: она видна в журнале согласий,
 * но ответом не считается и не мешает повторному опросу.
 */
class ConsentService
{
    /** @var class-string<BroadcastConsent> */
    private readonly string $consentModel;

    public function __construct(
        ?string $consentModel = null,
    ) {
        /** @var class-string<BroadcastConsent> $resolved */
        $resolved = $consentModel ?? config('filament-max-broadcasts.consent.consent_model', BroadcastConsent::class);
        $this->consentModel = $resolved;
    }

    public function optIn(int $chatId, ?int $userId, int $segmentId): void
    {
        $this->record($chatId, $userId, BroadcastConsentAction::OptIn, $segmentId);
    }

    /**
     * Фиксирует отправленный запрос согласия. Ответ (opt_in/opt_out) запросом не перезаписывается.
     */
    public function requestSent(int $chatId, ?int $userId, int $segmentId): void
    {
        $segment = $this->findSegment($segmentId);

        if ($segment === null) {
            return;
        }

        $this->requestSentForSegment($segment, $chatId, $userId);
    }

    /**
     * То же, что requestSent(), но без повторного чтения сегмента: джоба уже держит модель.
     */
    public function requestSentForSegment(BroadcastSegment $segment, int $chatId, ?int $userId): void
    {
        $modelClass = $this->consentModel;

        // Обновляем только pending-строку: ответ (opt_in/opt_out) запросом не перезаписывается.
        $updated = $modelClass::query()
            ->where('segment_id', $segment->getKey())
            ->where('chat_id', $chatId)
            ->where('action', BroadcastConsentAction::Pending->value)
            ->update(['user_id' => $userId, 'source' => 'request']);

        if ($updated > 0) {
            return;
        }

        // Строка есть и содержит ответ — оставляем её как есть.
        if ($modelClass::query()
            ->where('segment_id', $segment->getKey())
            ->where('chat_id', $chatId)
            ->exists()) {
            return;
        }

        $modelClass::query()->create([
            'segment_id' => $segment->getKey(),
            'chat_id' => $chatId,
            'user_id' => $userId,
            'action' => BroadcastConsentAction::Pending,
            'source' => 'request',
        ]);
    }

    public function optOut(int $chatId, ?int $userId, int $segmentId): void
    {
        $this->record($chatId, $userId, BroadcastConsentAction::OptOut, $segmentId);
    }

    public function findSegment(int $segmentId): ?BroadcastSegment
    {
        /** @var class-string<BroadcastSegment> $modelClass */
        $modelClass = config('filament-max-broadcasts.segment_model', BroadcastSegment::class);

        return $modelClass::query()->find($segmentId);
    }

    /**
     * chat_id всех, кто согласился (opt_in) в сегменте. Источник аудитории рассылки.
     *
     * @return list<int>
     */
    public function optedInChatIds(int $segmentId): array
    {
        $segment = $this->findSegment($segmentId);

        if ($segment === null) {
            return [];
        }

        $modelClass = $this->consentModel;

        return array_values($modelClass::query()
            ->where('segment_id', $segment->getKey())
            ->where('action', BroadcastConsentAction::OptIn->value)
            ->pluck('chat_id')
            ->map(static fn (mixed $chatId): int => is_scalar($chatId) ? (int) $chatId : 0)
            ->all());
    }

    /**
     * chat_id согласившихся (opt_in) по нескольким сегментам одним запросом.
     *
     * @param  list<int>  $segmentIds
     * @return array<int, list<int>> segment id => chat_id
     */
    public function optedInChatIdsBySegment(array $segmentIds): array
    {
        if ($segmentIds === []) {
            return [];
        }

        $modelClass = $this->consentModel;

        $rows = $modelClass::query()
            ->whereIn('segment_id', $segmentIds)
            ->where('action', BroadcastConsentAction::OptIn->value)
            ->get(['segment_id', 'chat_id']);

        $map = [];

        foreach ($rows as $row) {
            $segmentId = $row->getAttribute('segment_id');
            $chatId = $row->getAttribute('chat_id');

            if (! is_scalar($segmentId) || ! is_scalar($chatId)) {
                continue;
            }

            $map[(int) $segmentId][] = (int) $chatId;
        }

        return $map;
    }

    /**
     * chat_id всех, кто ответил (opt_in или opt_out) в сегменте.
     *
     * Источник дедупа для повторного запроса: ответившие запрос больше не получают,
     * проигнорировавшие — получают. Записи pending — это отправленные запросы, а не
     * ответы, поэтому они сюда не попадают. Записи других сегментов не участвуют.
     *
     * @return list<int>
     */
    public function answeredChatIds(int $segmentId): array
    {
        $segment = $this->findSegment($segmentId);

        if ($segment === null) {
            return [];
        }

        $modelClass = $this->consentModel;

        return array_values($modelClass::query()
            ->where('segment_id', $segment->getKey())
            ->where('action', '!=', BroadcastConsentAction::Pending->value)
            ->pluck('chat_id')
            ->map(static fn (mixed $chatId): int => is_scalar($chatId) ? (int) $chatId : 0)
            ->all());
    }

    private function record(int $chatId, ?int $userId, BroadcastConsentAction $action, int $segmentId): void
    {
        $segment = $this->findSegment($segmentId);

        if ($segment === null) {
            return;
        }

        $modelClass = $this->consentModel;

        $modelClass::query()->updateOrCreate(
            ['segment_id' => $segment->getKey(), 'chat_id' => $chatId],
            ['user_id' => $userId, 'action' => $action, 'source' => 'callback'],
        );
    }
}
