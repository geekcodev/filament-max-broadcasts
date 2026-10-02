<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Resources;

use Filament\Schemas\Schema;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastConsentResource;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class BroadcastConsentResourceMetadataTest extends TestCase
{
    public function testLabelsAreLocalized(): void
    {
        app()->setLocale('ru');

        self::assertSame('Согласие', BroadcastConsentResource::getModelLabel());
        self::assertSame('Согласия', BroadcastConsentResource::getPluralModelLabel());
        self::assertSame('Согласия', BroadcastConsentResource::getNavigationLabel());
    }

    public function testNavigationMetadata(): void
    {
        self::assertSame('heroicon-o-shield-check', BroadcastConsentResource::getNavigationIcon());
        self::assertSame(5, BroadcastConsentResource::getNavigationSort());
        self::assertSame('broadcast-consents', BroadcastConsentResource::getSlug());
        self::assertSame(BroadcastConsent::class, BroadcastConsentResource::getModel());
    }

    public function testNavigationGroupFallsBackToDefault(): void
    {
        config()->set('filament-max-broadcasts.ui.navigation_group', null);
        app()->setLocale('ru');

        self::assertSame(
            __('filament-max-broadcasts::broadcasts.resource.navigation_group'),
            BroadcastConsentResource::getNavigationGroup(),
        );

        config()->set('filament-max-broadcasts.ui.navigation_group', 'Рассылки MAX');

        self::assertSame('Рассылки MAX', BroadcastConsentResource::getNavigationGroup());
    }

    public function testResourceIsReadOnly(): void
    {
        $consent = new BroadcastConsent();

        self::assertFalse(BroadcastConsentResource::canCreate());
        self::assertFalse(BroadcastConsentResource::canEdit($consent));
    }

    public function testCanDeleteRequiresManagePermission(): void
    {
        $consent = new BroadcastConsent();

        self::assertFalse(BroadcastConsentResource::canDelete($consent));

        $this->actingAs(TestUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'can_manage_broadcasts' => true,
        ]));

        self::assertTrue(BroadcastConsentResource::canDelete($consent));
    }

    public function testFormIsEmpty(): void
    {
        $schema = BroadcastConsentResource::form(Schema::make());

        self::assertSame([], $schema->getComponents());
    }
}
