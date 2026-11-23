<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Fixtures;

use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;

/**
 * Резолвер хоста, подключаемый через `recipients.resolver`.
 */
class CustomResolver extends BroadcastRecipientsResolver
{
}
