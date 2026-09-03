<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use GeekCo\FilamentMaxBroadcasts\Contracts\BroadcastTypeContract;
use InvalidArgumentException;

/**
 * Реестр типов рассылок из конфига `types` (token => класс, реализующий контракт).
 * Опции/подписи/поведение берутся из экземпляра типа; неизвестный токен — строгое исключение
 * при создании, мягкий fallback (сырой токен/серый) в отображении.
 */
final class BroadcastTypes
{
    /**
     * Опции типов для форм/фильтров: type => label (порядок — из конфига).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (array_keys(config()->array('filament-max-broadcasts.types', [])) as $type) {
            $options[(string) $type] = self::label((string) $type);
        }

        return $options;
    }

    /**
     * Экземпляр типа по токену. Неизвестный/неконфиг реестра токен — InvalidArgumentException.
     */
    public static function instance(string $type): BroadcastTypeContract
    {
        $class = config("filament-max-broadcasts.types.$type");

        if (! is_string($class) || ! is_a($class, BroadcastTypeContract::class, true)) {
            throw new InvalidArgumentException(sprintf('Unknown broadcast type "%s".', $type));
        }

        /** @var class-string<BroadcastTypeContract> $class */
        return $class::fromToken($type);
    }

    public static function contains(string $type): bool
    {
        $class = config("filament-max-broadcasts.types.$type");

        return is_string($class) && is_a($class, BroadcastTypeContract::class, true);
    }

    public static function label(string $type): string
    {
        return self::instanceOrNull($type)?->label() ?? $type;
    }

    public static function badgeColor(string $type): string
    {
        return self::instanceOrNull($type)?->badgeColor() ?? 'gray';
    }

    private static function instanceOrNull(string $type): ?BroadcastTypeContract
    {
        try {
            return self::instance($type);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
