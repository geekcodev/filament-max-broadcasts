<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use Illuminate\Support\Facades\DB;

/**
 * Единая точка изменения сегмента согласий и записи фактов opt-in/opt-out.
 *
 * Сегмент согласий — именованный сегмент (config consent.segment_name), в который
 * слушатель HandleConsentCallback складывает chat_id согласившихся. Таблица
 * max_broadcast_consents хранит последний факт (action) по паре segment+chat.
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

    public function optIn(int $chatId, ?int $userId = null): void
    {
        $this->record($chatId, $userId, BroadcastConsentAction::OptIn);
    }

    public function optOut(int $chatId, ?int $userId = null): void
    {
        $this->record($chatId, $userId, BroadcastConsentAction::OptOut);
    }

    /**
     * Резолвим (создаём при отсутствии) сегмент «Новости и акции» по имени из конфига.
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
     * @return list<int> chat_id согласившихся
     */
    public function consentChatIds(): array
    {
        return $this->resolveConsentSegment()->chat_ids ?? [];
    }

    /**
     * chat_id всех, кто ответил на запрос (opt_in или opt_out) именно для
     * сегмента «Новости и акции» (consent.segment_name).
     *
     * Источник дедупа для повторной рассылки запроса: ответившие больше
     * запрос не получают, а проигнорировавшие (без записи) — получают снова.
     * Записи, привязанные к другим сегментам, в дедупе не участвуют.
     *
     * @return list<int>
     */
    public function answeredChatIds(): array
    {
        $segment = $this->resolveConsentSegment();
        $modelClass = $this->consentModel;

        return array_values($modelClass::query()
            ->where('segment_id', $segment->getKey())
            ->get()
            ->map(static fn (BroadcastConsent $consent): int => $consent->chat_id)
            ->all());
    }

    private function record(int $chatId, ?int $userId, BroadcastConsentAction $action): void
    {
        $segment = $this->resolveConsentSegment();
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
