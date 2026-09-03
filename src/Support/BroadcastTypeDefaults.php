<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use GeekCo\MaxPhpClient\Dto\InlineKeyboardButton;
use GeekCo\MaxPhpClient\Dto\InlineKeyboardButtonRow;
use GeekCo\MaxPhpClient\Enum\ButtonType;

/**
 * Дефолтное поведение типов рассылок.
 *
 * Подписи — из lang (`broadcasts.type.<token>`), кнопки-диплинки — из
 * `buttons.per_type.<token>` + `bot_username`. Хост-тип подключает трейт
 * и переопределяет только то, что отличается.
 */
trait BroadcastTypeDefaults
{
    public static function fromToken(string $token): static
    {
        return static::from($token);
    }

    public function label(): string
    {
        return __("filament-max-broadcasts::broadcasts.type.{$this->value}");
    }

    /**
     * @return list<InlineKeyboardButtonRow>
     */
    public function buttonRows(): array
    {
        $botUsername = config()->string('filament-max-broadcasts.bot_username', '');

        if ($botUsername === '') {
            return [];
        }

        /** @var list<array{text: string, startapp: string}> $buttons */
        $buttons = config()->array("filament-max-broadcasts.buttons.per_type.{$this->value}", []);

        if ($buttons === []) {
            return [];
        }

        $row = [];

        foreach ($buttons as $button) {
            $row[] = new InlineKeyboardButton(
                type: ButtonType::Link,
                text: (string) $button['text'],
                url: $this->appDeepLink($botUsername, (string) $button['startapp']),
            );
        }

        return [new InlineKeyboardButtonRow($row)];
    }

    public function badgeColor(): string
    {
        return 'gray';
    }

    private function appDeepLink(string $botUsername, string $startParam): string
    {
        return sprintf('https://max.ru/%s?startapp=%s', $botUsername, $startParam);
    }
}
