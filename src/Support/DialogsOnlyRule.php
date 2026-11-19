<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Серверная проверка: в сегменте допускаются только чаты типа «диалог».
 */
class DialogsOnlyRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ids = is_array($value) ? array_values(array_filter($value, is_scalar(...))) : [];

        // Служебное значение «Выбрать всех» разворачивается в список чатов при сохранении.
        $ids = array_values(array_filter(
            $ids,
            static fn (mixed $id): bool => $id !== ChatSelectionField::SELECT_ALL,
        ));

        $notDialogs = array_diff(array_map('intval', $ids), ChatSelectionField::dialogIdsAmong($ids));

        if ($notDialogs !== []) {
            $fail(__('filament-max-broadcasts::broadcasts.segment.form.dialogs_only_error'));
        }
    }
}
