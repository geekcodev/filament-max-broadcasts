<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts;

use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSender;
use GeekCo\MaxPhpClient\ApiClient;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

class FilamentMaxBroadcastsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-max-broadcasts.php', 'filament-max-broadcasts');

        $this->app->singleton(BroadcastSender::class, static fn (Container $app): BroadcastSender => new BroadcastSender(
            $app->make(ApiClient::class),
        ));

        $this->app->singleton(BroadcastRecipientsResolver::class, static function (): BroadcastRecipientsResolver {
            /** @var class-string<BroadcastRecipientsResolver>|null $resolver */
            $resolver = config('filament-max-broadcasts.recipients.resolver');

            if ($resolver !== null && $resolver !== BroadcastRecipientsResolver::class) {
                /** @var BroadcastRecipientsResolver $instance */
                $instance = app($resolver);

                return $instance;
            }

            return new BroadcastRecipientsResolver();
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-max-broadcasts');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-max-broadcasts.php' => $this->app->configPath('filament-max-broadcasts.php'),
            ], 'filament-max-broadcasts-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'filament-max-broadcasts-migrations');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/filament-max-broadcasts'),
            ], 'filament-max-broadcasts-lang');
        }
    }
}
