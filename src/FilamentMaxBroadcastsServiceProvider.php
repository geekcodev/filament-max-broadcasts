<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts;

use GeekCo\FilamentMaxBroadcasts\Listeners\HandleConsentCallback;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSender;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\LaravelMaxClient\Webhook\MaxUpdateReceived;
use GeekCo\MaxPhpClient\ApiClient;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
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

        $this->app->singleton(ConsentService::class, static function (): ConsentService {
            /** @var class-string<BroadcastConsent>|null $model */
            $model = config('filament-max-broadcasts.consent.consent_model');

            return new ConsentService($model);
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-max-broadcasts');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->app->make(Dispatcher::class)->listen(MaxUpdateReceived::class, HandleConsentCallback::class);

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
