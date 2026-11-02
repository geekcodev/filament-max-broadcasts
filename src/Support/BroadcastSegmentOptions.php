<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSegmentRecipients;

/**
 * Варианты селекта сегментов при создании рассылки: только сегменты с получателями,
 * в подписи — название и бейдж числа получателей (активные диалоги).
 */
class BroadcastSegmentOptions
{
    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $segments = BroadcastSegment::query()->orderBy('name')->get();

        $counts = (new BroadcastSegmentRecipients())->dialogCounts($segments);

        $options = [];

        foreach ($segments as $segment) {
            /** @var int $key */
            $key = $segment->getKey();
            $count = $counts[$key] ?? 0;

            if ($count === 0) {
                continue;
            }

            $options[$key] = self::label((string) $segment->name, $count);
        }

        return $options;
    }

    /**
     * Подписи уже выбранных сегментов без бейджа: сегмент, ставший пустым, остаётся валидным значением формы.
     *
     * @param  array<mixed>  $values
     * @return array<int, string>
     */
    public static function labelsFor(array $values): array
    {
        $ids = array_values(array_filter(
            array_map(static fn (mixed $value): int => is_scalar($value) ? (int) $value : 0, $values),
            static fn (int $id): bool => $id > 0,
        ));

        if ($ids === []) {
            return [];
        }

        $labels = [];

        foreach (BroadcastSegment::query()->whereIn('id', $ids)->get() as $segment) {
            /** @var int $key */
            $key = $segment->getKey();
            $labels[$key] = e((string) $segment->name);
        }

        return $labels;
    }

    private static function label(string $name, int $count): string
    {
        return sprintf(
            '<span style="display:inline-flex;align-items:center;gap:.5rem;min-width:0"><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">%s</span><span class="fi-badge fi-size-sm" style="background-color:var(--primary-50);color:var(--primary-700)">%d</span></span>',
            e($name),
            $count,
        );
    }
}
