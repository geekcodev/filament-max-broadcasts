<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Enums;

enum BroadcastRecipientStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('filament-max-broadcasts::broadcasts.recipient_status.pending'),
            self::Sent => __('filament-max-broadcasts::broadcasts.recipient_status.sent'),
            self::Failed => __('filament-max-broadcasts::broadcasts.recipient_status.failed'),
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
