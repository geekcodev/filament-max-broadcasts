<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Enums;

enum BroadcastConsentAction: string
{
    case OptIn = 'opt_in';
    case OptOut = 'opt_out';

    public function label(): string
    {
        return match ($this) {
            self::OptIn => __('filament-max-broadcasts::broadcasts.consent.action.opt_in'),
            self::OptOut => __('filament-max-broadcasts::broadcasts.consent.action.opt_out'),
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
