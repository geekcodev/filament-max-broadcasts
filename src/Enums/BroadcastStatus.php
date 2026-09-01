<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Enums;

enum BroadcastStatus: string
{
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('filament-max-broadcasts::broadcasts.status.scheduled'),
            self::Running => __('filament-max-broadcasts::broadcasts.status.running'),
            self::Completed => __('filament-max-broadcasts::broadcasts.status.completed'),
            self::Cancelled => __('filament-max-broadcasts::broadcasts.status.cancelled'),
            self::Failed => __('filament-max-broadcasts::broadcasts.status.failed'),
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
