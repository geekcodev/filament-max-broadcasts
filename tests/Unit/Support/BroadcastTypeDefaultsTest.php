<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\Promo;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\MaxPhpClient\Enum\ButtonType;

class BroadcastTypeDefaultsTest extends TestCase
{
    public function testEmptyWhenBotUsernameMissing(): void
    {
        config()->set('filament-max-broadcasts.bot_username', '');
        config()->set('filament-max-broadcasts.buttons.per_type.promo', [
            ['text' => 'Button', 'startapp' => 'param'],
        ]);

        self::assertSame([], Promo::Promo->buttonRows());
    }

    public function testEmptyWhenNoButtonsConfiguredForType(): void
    {
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.buttons.per_type.promo', []);

        self::assertSame([], Promo::Promo->buttonRows());
    }

    public function testTypeWithoutConfigurationGetsNoButtons(): void
    {
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.buttons.per_type', [
            'promo' => [['text' => 'Booking', 'startapp' => 'booking']],
        ]);

        self::assertSame([], News::News->buttonRows());
    }

    public function testBuildsDeepLinkButtons(): void
    {
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.buttons.per_type', [
            'promo' => [
                ['text' => 'Booking', 'startapp' => 'booking'],
                ['text' => 'Support', 'startapp' => 'consult'],
            ],
        ]);

        $rows = Promo::Promo->buttonRows();

        self::assertCount(1, $rows);

        $buttons = $rows[0]->buttons;

        self::assertCount(2, $buttons);

        self::assertSame(ButtonType::Link, $buttons[0]->type);
        self::assertSame('Booking', $buttons[0]->text);
        self::assertSame('https://max.ru/mybot?startapp=booking', $buttons[0]->url);

        self::assertSame(ButtonType::Link, $buttons[1]->type);
        self::assertSame('Support', $buttons[1]->text);
        self::assertSame('https://max.ru/mybot?startapp=consult', $buttons[1]->url);
    }

    public function testDefaultLabelComesFromLang(): void
    {
        app()->setLocale('en');

        self::assertSame('News', News::News->label());
        self::assertSame('Promo', Promo::Promo->label());
    }

    public function testDefaultBadgeColors(): void
    {
        self::assertSame('gray', News::News->badgeColor());
        self::assertSame('success', Promo::Promo->badgeColor());
    }

    public function testFromTokenResolvesMatchingCase(): void
    {
        self::assertSame('gray', News::fromToken('news')->badgeColor());
        self::assertSame('success', Promo::fromToken('promo')->badgeColor());
    }
}
