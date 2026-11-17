<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes;

use GeekCo\FilamentMaxBroadcasts\Contracts\BroadcastTypeContract;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypeDefaults;
use GeekCo\MaxPhpClient\Dto\Button\CallbackButton;

/**
 * Кнопки запроса согласия для SendConsentRequestsJob.
 *
 * Тип НЕ регистрируется в реестре types — запрос согласия не создаётся как
 * обычная рассылка, а рассылается фиксированным сообщением по кнопке
 * «Запросить согласие» в сегменте. Нажатие обрабатывается слушателем
 * HandleConsentCallback.
 *
 * Payload кнопок всегда содержит сегмент: consent:<segmentId>:<action>. Поэтому
 * контрактный buttonRows() пуст: запрос без сегмента отправить нельзя, а кнопки
 * строятся только через buttonRowsFor().
 */
enum ConsentPoll: string implements BroadcastTypeContract
{
    use BroadcastTypeDefaults;

    case ConsentPoll = 'consent';

    public function badgeColor(): string
    {
        return 'success';
    }

    /**
     * Контракт требует метод, но у опроса согласия нет кнопок без сегмента.
     *
     * @return list<list<\GeekCo\MaxPhpClient\Dto\Button>>
     */
    public function buttonRows(): array
    {
        return [];
    }

    /**
     * @return list<list<\GeekCo\MaxPhpClient\Dto\Button>>
     */
    public static function buttonRowsFor(int $segmentId): array
    {
        return self::callbackRows($segmentId);
    }

    public static function payload(int $segmentId, string $action): string
    {
        $prefix = config()->string('filament-max-broadcasts.consent.payload_prefix', 'consent');

        return $prefix.':'.$segmentId.':'.$action;
    }

    /**
     * @return list<list<\GeekCo\MaxPhpClient\Dto\Button>>
     */
    private static function callbackRows(int $segmentId): array
    {
        return [[
            new CallbackButton(
                config()->string('filament-max-broadcasts.consent.button_text_opt_in', 'Да'),
                self::payload($segmentId, 'opt_in'),
            ),
            new CallbackButton(
                config()->string('filament-max-broadcasts.consent.button_text_opt_out', 'Нет'),
                self::payload($segmentId, 'opt_out'),
            ),
        ]];
    }
}
