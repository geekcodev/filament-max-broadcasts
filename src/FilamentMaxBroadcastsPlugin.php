<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts;

use Filament\Contracts\Plugin;
use Filament\Panel;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastResource;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;

class FilamentMaxBroadcastsPlugin implements Plugin
{
    /** @var class-string */
    protected string $resource = BroadcastResource::class;

    /** @var class-string */
    protected string $segmentResource = BroadcastSegmentResource::class;

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

    /**
     * @param  class-string  $segmentResource
     */
    public function segmentResource(string $segmentResource): static
    {
        $this->segmentResource = $segmentResource;

        return $this;
    }

    public function getId(): string
    {
        return 'filament-max-broadcasts';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            $this->resource,
            $this->segmentResource,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
