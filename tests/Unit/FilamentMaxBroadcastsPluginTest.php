<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit;

use Filament\Facades\Filament;
use GeekCo\FilamentMaxBroadcasts\FilamentMaxBroadcastsPlugin;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastConsentResource;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastResource;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class FilamentMaxBroadcastsPluginTest extends TestCase
{
    public function testPluginId(): void
    {
        self::assertSame('filament-max-broadcasts', FilamentMaxBroadcastsPlugin::make()->getId());
    }

    public function testPanelRegistersPackageResourcesByDefault(): void
    {
        $resources = Filament::getPanel('admin')->getResources();

        self::assertContains(BroadcastResource::class, $resources);
        self::assertContains(BroadcastSegmentResource::class, $resources);
        self::assertContains(BroadcastConsentResource::class, $resources);
    }

    public function testResourcesCanBeOverridden(): void
    {
        $panel = Filament::getPanel('admin');

        $plugin = FilamentMaxBroadcastsPlugin::make()
            ->resource(BroadcastSegmentResource::class)
            ->segmentResource(BroadcastConsentResource::class)
            ->consentResource(BroadcastResource::class);

        $plugin->register($panel);
        $plugin->boot($panel);

        $resources = $panel->getResources();

        self::assertContains(BroadcastSegmentResource::class, $resources);
        self::assertContains(BroadcastConsentResource::class, $resources);
        self::assertContains(BroadcastResource::class, $resources);
    }
}
