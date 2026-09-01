<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Enums;

enum BroadcastType: string
{
    case News = 'news';
    case Promo = 'promo';

    public function label(): string
    {
        return match ($this) {
            self::News => __('filament-max-broadcasts::broadcasts.type.news'),
            self::Promo => __('filament-max-broadcasts::broadcasts.type.promo'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }
}
