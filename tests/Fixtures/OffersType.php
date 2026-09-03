<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Fixtures;

use GeekCo\FilamentMaxBroadcasts\Contracts\BroadcastTypeContract;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypeDefaults;

enum OffersType: string implements BroadcastTypeContract
{
    use BroadcastTypeDefaults;

    case Offers = 'offers';

    public function label(): string
    {
        return 'Акции магазина';
    }
}
