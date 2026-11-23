<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit;

use Filament\Facades\Filament;
use Filament\Panel;
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
        $resources = $this->adminPanel()->getResources();

        self::assertContains(BroadcastResource::class, $resources);
        self::assertContains(BroadcastSegmentResource::class, $resources);
        self::assertContains(BroadcastConsentResource::class, $resources);
    }

    public function testResourcesCanBeOverridden(): void
    {
        $panel = $this->adminPanel();

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

    /**
     * Возвращаемый тип getPanel() менялся между минорными версиями Filament
     * (Panel и Panel|null), поэтому тип фиксируется явно и проверяется в тесте.
     */
    private function adminPanel(): Panel
    {
        /** @var Panel|null $panel */
        $panel = Filament::getPanel('admin');

        self::assertInstanceOf(Panel::class, $panel);

        return $panel;
    }
}
