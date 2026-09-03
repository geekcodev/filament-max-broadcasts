<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Contracts;

use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypeDefaults;
use GeekCo\MaxPhpClient\Dto\InlineKeyboardButtonRow;

/**
 * Тип рассылки как поведение.
 *
 * Каждый тип — отдельный backed-enum, реализующий контракт; регистрируется в
 * конфиге `types` (token => class). Простейшие типы строятся на трейте
 * {@see BroadcastTypeDefaults}; для собственного поведения переопределяется
 * нужный метод (например, `buttonRows()` или `badgeColor()`).
 */
interface BroadcastTypeContract
{
    /**
     * Возвращает экземпляр типа по токену из реестра (case value === token).
     */
    public static function fromToken(string $token): static;

    public function label(): string;

    /**
     * Ряды inline-кнопок для типа (пусто — кнопок нет).
     *
     * @return list<InlineKeyboardButtonRow>
     */
    public function buttonRows(): array;

    public function badgeColor(): string;
}
