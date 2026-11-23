<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Support\QueueLockTtl;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class QueueLockTtlTest extends TestCase
{
    public function testCoversJobTimeoutWhenConfiguredTtlIsShorter(): void
    {
        config()->set('filament-max-broadcasts.queue.lock_ttl_seconds', 600);
        config()->set('filament-max-broadcasts.queue.timeout', 3600);

        self::assertSame(3660, QueueLockTtl::seconds());
    }

    public function testKeepsLongerConfiguredTtl(): void
    {
        config()->set('filament-max-broadcasts.queue.lock_ttl_seconds', 7200);
        config()->set('filament-max-broadcasts.queue.timeout', 3600);

        self::assertSame(7260, QueueLockTtl::seconds());
    }

    public function testUsesPackageDefaults(): void
    {
        config()->set('filament-max-broadcasts.queue.lock_ttl_seconds', 600);
        config()->set('filament-max-broadcasts.queue.timeout', 3600);

        self::assertSame(3660, QueueLockTtl::seconds());
    }
}
