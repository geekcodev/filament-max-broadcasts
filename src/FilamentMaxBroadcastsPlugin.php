<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts;

use Filament\Contracts\Plugin;
use Filament\Panel;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastResource;

class FilamentMaxBroadcastsPlugin implements Plugin
{
    /** @var class-string */
    protected string $resource = BroadcastResource::class;

    public static function make(): static
    {
        /** @var static */
        return app(static::class);
    }

    /**
     * @param  class-string  $resource
     */
    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    public function getId(): string
    {
        return 'filament-max-broadcasts';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([$this->resource]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
