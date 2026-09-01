<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests;

use GeekCo\FilamentMaxBroadcasts\FilamentMaxBroadcastsServiceProvider;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\AdminPanelProvider;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\LaravelMaxClient\MaxServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../vendor/geekcodev/laravel-max-client/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/Migrations');

        $this->app['db']->connection()->getPdo()->exec('PRAGMA foreign_keys = ON');

        Queue::fake();

        Gate::define(
            'broadcasts.view',
            static fn (?TestUser $user): bool => $user?->can_view_broadcasts ?? false,
        );
        Gate::define(
            'broadcasts.create',
            static fn (?TestUser $user): bool => $user?->can_create_broadcasts ?? false,
        );
        Gate::define(
            'broadcasts.send',
            static fn (?TestUser $user): bool => $user?->can_send_broadcasts ?? false,
        );
        Gate::define(
            'broadcasts.manage',
            static fn (?TestUser $user): bool => $user?->can_manage_broadcasts ?? false,
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Infolists\InfolistsServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            \Filament\Schemas\SchemasServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Tables\TablesServiceProvider::class,
            \Filament\Widgets\WidgetsServiceProvider::class,
            MaxServiceProvider::class,
            FilamentMaxBroadcastsServiceProvider::class,
            AdminPanelProvider::class,

            // Livewire — строго после Filament: SupportServiceProvider перебивает
            // биндинг DataStore non-shared bind(), а LivewireServiceProvider::register()
            // закрепляет механизмы через instance(). Иначе хранилище состояния Livewire
            // теряется между вызовами.
            \Livewire\LivewireServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filament-max-broadcasts.user_model', Fixtures\TestUser::class);

        $app['config']->set('laravel-max-client.api_token', 'test-token');
        $app['config']->set('laravel-max-client.retry.base_delay_seconds', 0.0);
    }
}
