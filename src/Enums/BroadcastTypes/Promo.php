<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes;

use GeekCo\FilamentMaxBroadcasts\Contracts\BroadcastTypeContract;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypeDefaults;

enum Promo: string implements BroadcastTypeContract
{
    use BroadcastTypeDefaults;

    case Promo = 'promo';

    public function badgeColor(): string
    {
        return 'success';
    }
}
