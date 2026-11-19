<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use GeekCo\MaxPhpClient\Dto\Button\CallbackButton;

/**
 * Кнопки запроса согласия для SendConsentRequestsJob.
 *
 * Payload всегда содержит сегмент: consent:<segmentId>:<action>. Без сегмента
 * кнопки не строятся — опрос без сегмента отправить нельзя.
 */
final class ConsentButtons
{
    public static function payload(int $segmentId, string $action): string
    {
        $prefix = config()->string('filament-max-broadcasts.consent.payload_prefix', 'consent');

        return $prefix.':'.$segmentId.':'.$action;
    }

    /**
     * @return list<list<\GeekCo\MaxPhpClient\Dto\Button>>
     */
    public static function forSegment(int $segmentId): array
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
