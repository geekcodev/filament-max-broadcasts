<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Events;

use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Broadcast $broadcast)
    {
    }
}
