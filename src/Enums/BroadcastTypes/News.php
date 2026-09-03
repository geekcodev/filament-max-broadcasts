<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes;

use GeekCo\FilamentMaxBroadcasts\Contracts\BroadcastTypeContract;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypeDefaults;

enum News: string implements BroadcastTypeContract
{
    use BroadcastTypeDefaults;

    case News = 'news';
}
