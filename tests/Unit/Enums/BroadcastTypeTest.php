<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Enums;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastType;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class BroadcastTypeTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame('news', BroadcastType::News->value);
        self::assertSame('promo', BroadcastType::Promo->value);
    }

    public function testLabel(): void
    {
        app()->setLocale('en');

        self::assertSame('News', BroadcastType::News->label());
        self::assertSame('Promo', BroadcastType::Promo->label());
    }

    public function testRussianLabels(): void
    {
        app()->setLocale('ru');

        self::assertSame('Новость', BroadcastType::News->label());
        self::assertSame('Акция', BroadcastType::Promo->label());
    }

    public function testLabels(): void
    {
        app()->setLocale('en');

        self::assertSame([
            'news' => 'News',
            'promo' => 'Promo',
        ], BroadcastType::labels());
    }
}
