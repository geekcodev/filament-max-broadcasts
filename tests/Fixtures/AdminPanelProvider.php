<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use GeekCo\FilamentMaxBroadcasts\FilamentMaxBroadcastsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->default()
            ->plugin(FilamentMaxBroadcastsPlugin::make());
    }
}
