<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use Closure;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSegmentRecipients;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Серверная проверка: у выбранных сегментов есть получатели с согласием.
 *
 * Рассылка по сегменту уходит только согласившимся диалогам, поэтому сегмент без единого
 * opt_in отправлять некуда. Группы и каналы из поля получателей это правило не отменяют.
 */
class SegmentConsentRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ids = is_array($value)
            ? array_values(array_unique(array_map('intval', array_filter($value, is_scalar(...)))))
            : [];

        if ($ids === []) {
            return;
        }

        $segments = BroadcastSegment::query()->whereIn('id', $ids)->get();

        if (array_sum((new BroadcastSegmentRecipients())->consentedCounts($segments)) === 0) {
            $fail(__('filament-max-broadcasts::broadcasts.form.segments_no_consent'));
        }
    }
}
