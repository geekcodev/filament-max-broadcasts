<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\Promo;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastTypes;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\OffersType;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use InvalidArgumentException;

class BroadcastTypesTest extends TestCase
{
    private function setTypes(): void
    {
        config()->set('filament-max-broadcasts.types', [
            'news' => News::class,
            'promo' => Promo::class,
            'offers' => OffersType::class,
        ]);
    }

    public function testInstanceReturnsContracts(): void
    {
        $this->setTypes();

        self::assertSame(News::News, BroadcastTypes::instance('news'));
        self::assertSame(Promo::Promo, BroadcastTypes::instance('promo'));
        self::assertSame(OffersType::Offers, BroadcastTypes::instance('offers'));
    }

    public function testInstanceThrowsForUnknownType(): void
    {
        $this->setTypes();

        $this->expectException(InvalidArgumentException::class);

        BroadcastTypes::instance('unknown-type');
    }

    public function testContainsChecksRegistry(): void
    {
        $this->setTypes();
        config()->set('filament-max-broadcasts.types', [
            'news' => News::class,
            'offers' => OffersType::class,
        ]);

        self::assertTrue(BroadcastTypes::contains('news'));
        self::assertTrue(BroadcastTypes::contains('offers'));
        self::assertFalse(BroadcastTypes::contains('promo'));
    }

    public function testOptionsUseTypeLabels(): void
    {
        $this->setTypes();
        app()->setLocale('en');

        self::assertSame([
            'news' => 'News',
            'promo' => 'Promo',
            'offers' => 'Акции магазина',
        ], BroadcastTypes::options());
    }

    public function testLabelFallsBackToRawToken(): void
    {
        config()->set('filament-max-broadcasts.types', []);

        self::assertSame('weird', BroadcastTypes::label('weird'));
    }

    public function testBadgeColorUsesType(): void
    {
        $this->setTypes();

        self::assertSame('gray', BroadcastTypes::badgeColor('news'));
        self::assertSame('success', BroadcastTypes::badgeColor('promo'));
    }
}
