<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes;

use GeekCo\FilamentMaxBroadcasts\Contracts\BroadcastTypeContract;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypeDefaults;
use GeekCo\MaxPhpClient\Dto\InlineKeyboardButton;
use GeekCo\MaxPhpClient\Dto\InlineKeyboardButtonRow;
use GeekCo\MaxPhpClient\Enum\ButtonType;

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
     * @return list<InlineKeyboardButtonRow>
     */
    public function buttonRows(): array
    {
        return [];
    }

    /**
     * @return list<InlineKeyboardButtonRow>
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
     * @return list<InlineKeyboardButtonRow>
     */
    private static function callbackRows(int $segmentId): array
    {
        return [
            new InlineKeyboardButtonRow([
                new InlineKeyboardButton(
                    type: ButtonType::Callback,
                    text: config()->string('filament-max-broadcasts.consent.button_text_opt_in', 'Да'),
                    payload: self::payload($segmentId, 'opt_in'),
                ),
                new InlineKeyboardButton(
                    type: ButtonType::Callback,
                    text: config()->string('filament-max-broadcasts.consent.button_text_opt_out', 'Нет'),
                    payload: self::payload($segmentId, 'opt_out'),
                ),
            ]),
        ];
    }
}
