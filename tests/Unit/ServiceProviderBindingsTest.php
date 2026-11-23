<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit;

use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\CustomResolver;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class ServiceProviderBindingsTest extends TestCase
{
    public function testConfiguredResolverIsResolvedFromContainer(): void
    {
        config()->set('filament-max-broadcasts.recipients.resolver', CustomResolver::class);
        app()->forgetInstance(BroadcastRecipientsResolver::class);

        self::assertInstanceOf(CustomResolver::class, app(BroadcastRecipientsResolver::class));
    }

    public function testDefaultResolverIsUsedWithoutCustomConfiguration(): void
    {
        app()->forgetInstance(BroadcastRecipientsResolver::class);

        self::assertSame(BroadcastRecipientsResolver::class, get_class(app(BroadcastRecipientsResolver::class)));
    }
}
