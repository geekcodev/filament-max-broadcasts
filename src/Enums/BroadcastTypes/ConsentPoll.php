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
 * «Запросить согласие». Нажатие обрабатывается слушателем HandleConsentCallback.
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
     * Кнопки согласия — callback (без диплинка). Payload: consent:<action>.
     *
     * @return list<InlineKeyboardButtonRow>
     */
    public function buttonRows(): array
    {
        $prefix = config()->string('filament-max-broadcasts.consent.payload_prefix', 'consent');

        return [
            new InlineKeyboardButtonRow([
                new InlineKeyboardButton(
                    type: ButtonType::Callback,
                    text: config()->string('filament-max-broadcasts.consent.button_text_opt_in', 'Согласен'),
                    payload: $prefix.':opt_in',
                ),
                new InlineKeyboardButton(
                    type: ButtonType::Callback,
                    text: config()->string('filament-max-broadcasts.consent.button_text_opt_out', 'Не согласен'),
                    payload: $prefix.':opt_out',
                ),
            ]),
        ];
    }
}
