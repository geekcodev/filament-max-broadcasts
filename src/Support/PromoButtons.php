<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use GeekCo\MaxPhpClient\Dto\InlineKeyboardButton;
use GeekCo\MaxPhpClient\Dto\InlineKeyboardButtonRow;
use GeekCo\MaxPhpClient\Enum\ButtonType;

/**
 * Сборка кнопок-диплинков акций (тип «Акция») из конфига плагина.
 * Если bot_username пуст или список promo_buttons пуст — кнопок нет.
 */
class PromoButtons
{
    /**
     * @return list<InlineKeyboardButtonRow>
     */
    public function rows(): array
    {
        $botUsername = config()->string('filament-max-broadcasts.bot_username', '');

        if ($botUsername === '') {
            return [];
        }

        /** @var list<array{text: string, startapp: string}> $buttons */
        $buttons = config()->array('filament-max-broadcasts.promo_buttons', []);

        if ($buttons === []) {
            return [];
        }

        $row = [];

        foreach ($buttons as $button) {
            $row[] = new InlineKeyboardButton(
                type: ButtonType::Link,
                text: (string) $button['text'],
                url: self::appDeepLink($botUsername, (string) $button['startapp']),
            );
        }

        return [new InlineKeyboardButtonRow($row)];
    }

    public static function appDeepLink(string $botUsername, string $startParam): string
    {
        return sprintf('https://max.ru/%s?startapp=%s', $botUsername, $startParam);
    }
}
