<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use Illuminate\Support\Facades\DB;

/**
 * Единая точка изменения согласий и записи фактов opt-in/opt-out.
 *
 * Согласие привязано к сегменту, из которого был запрошен: слушатель
 * HandleConsentCallback складывает chat_id согласившихся в сегмент из payload кнопки
 * (payload без сегмента больше не принимается). Необязательный segmentId сохранён для
 * обратной совместимости: без него метод работает со стартовым сегментом
 * «Новости и акции» (config consent.segment_name). Таблица max_broadcast_consents
 * хранит последний факт (action) по паре segment+chat.
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

    public function optIn(int $chatId, ?int $userId = null, ?int $segmentId = null): void
    {
        $this->record($chatId, $userId, BroadcastConsentAction::OptIn, $segmentId);
    }

    public function optOut(int $chatId, ?int $userId = null, ?int $segmentId = null): void
    {
        $this->record($chatId, $userId, BroadcastConsentAction::OptOut, $segmentId);
    }

    /**
     * Сегмент по id; null — стартовый сегмент «Новости и акции» (создаётся при отсутствии).
     */
    public function findSegment(?int $segmentId): ?BroadcastSegment
    {
        if ($segmentId === null) {
            return $this->resolveConsentSegment();
        }

        /** @var class-string<BroadcastSegment> $modelClass */
        $modelClass = config('filament-max-broadcasts.segment_model', BroadcastSegment::class);

        return $modelClass::query()->find($segmentId);
    }

    /**
     * Резолвим (создаём при отсутствии) стартовый сегмент «Новости и акции» по имени из конфига.
     */
    public function resolveConsentSegment(): BroadcastSegment
    {
        /** @var class-string<BroadcastSegment> $modelClass */
        $modelClass = config('filament-max-broadcasts.segment_model', BroadcastSegment::class);

        $name = config()->string('filament-max-broadcasts.consent.segment_name', 'Новости и акции');

        return $modelClass::query()->firstOrCreate(
            ['name' => $name],
            ['chat_ids' => []],
        );
    }

    /**
     * @return list<int> chat_id согласившихся в стартовом сегменте
     */
    public function consentChatIds(): array
    {
        return $this->resolveConsentSegment()->chat_ids ?? [];
    }

    /**
     * chat_id всех, кто ответил на запрос (opt_in или opt_out) в сегменте.
     *
     * Источник дедупа для повторного запроса: ответившие в этом сегменте запрос больше
     * не получают, проигнорировавшие — получают. Записи других сегментов не участвуют.
     *
     * @return list<int>
     */
    public function answeredChatIds(?int $segmentId = null): array
    {
        $segment = $this->findSegment($segmentId);

        if ($segment === null) {
            return [];
        }

        $modelClass = $this->consentModel;

        return array_values($modelClass::query()
            ->where('segment_id', $segment->getKey())
            ->get()
            ->map(static fn (BroadcastConsent $consent): int => $consent->chat_id)
            ->all());
    }

    private function record(int $chatId, ?int $userId, BroadcastConsentAction $action, ?int $segmentId): void
    {
        $segment = $this->findSegment($segmentId);

        if ($segment === null) {
            return;
        }

        $modelClass = $this->consentModel;

        DB::transaction(function () use ($modelClass, $segment, $chatId, $userId, $action): void {
            $modelClass::query()
                ->updateOrCreate(
                    ['segment_id' => $segment->getKey(), 'chat_id' => $chatId],
                    ['user_id' => $userId, 'action' => $action, 'source' => 'callback'],
                );

            // chat_ids сегмента — производное от таблицы согласий (opt-in): пересчитываем
            // в той же транзакции вместо read-modify-write, чтобы параллельные callback'и
            // не теряли запись (lost update) и список самовосстанавливался.
            $chatIds = array_values(
                $modelClass::query()
                    ->where('segment_id', $segment->getKey())
                    ->where('action', BroadcastConsentAction::OptIn->value)
                    ->get()
                    ->map(static fn (BroadcastConsent $consent): int => $consent->chat_id)
                    ->all(),
            );

            $segment->update(['chat_ids' => $chatIds]);
        });
    }
}
