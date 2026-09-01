<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Support\PromoButtons;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\MaxPhpClient\Enum\ButtonType;

class PromoButtonsTest extends TestCase
{
    public function testEmptyWhenBotUsernameMissing(): void
    {
        config()->set('filament-max-broadcasts.bot_username', '');
        config()->set('filament-max-broadcasts.promo_buttons', [
            ['text' => 'Button', 'startapp' => 'param'],
        ]);

        self::assertSame([], (new PromoButtons())->rows());
    }

    public function testEmptyWhenNoButtonsConfigured(): void
    {
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.promo_buttons', []);

        self::assertSame([], (new PromoButtons())->rows());
    }

    public function testBuildsDeepLinkButtons(): void
    {
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.promo_buttons', [
            ['text' => 'Booking', 'startapp' => 'booking'],
            ['text' => 'Support', 'startapp' => 'consult'],
        ]);

        $rows = (new PromoButtons())->rows();

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
}
