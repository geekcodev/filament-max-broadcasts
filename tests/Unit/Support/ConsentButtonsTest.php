<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Support\ConsentButtons;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\MaxPhpClient\Dto\Button\CallbackButton;

class ConsentButtonsTest extends TestCase
{
    public function testPayloadContainsSegmentAndAction(): void
    {
        self::assertSame('consent:7:opt_in', ConsentButtons::payload(7, 'opt_in'));
        self::assertSame('consent:7:opt_out', ConsentButtons::payload(7, 'opt_out'));

        config()->set('filament-max-broadcasts.consent.payload_prefix', 'vote');

        self::assertSame('vote:7:opt_in', ConsentButtons::payload(7, 'opt_in'));
    }

    public function testButtonsCarrySegmentIdAndConfiguredTexts(): void
    {
        $rows = ConsentButtons::forSegment(7);

        self::assertCount(1, $rows);
        self::assertInstanceOf(CallbackButton::class, $rows[0][0]);
        self::assertInstanceOf(CallbackButton::class, $rows[0][1]);
        self::assertSame('consent:7:opt_in', $rows[0][0]->payload);
        self::assertSame('consent:7:opt_out', $rows[0][1]->payload);
        self::assertSame('Да', $rows[0][0]->text);
        self::assertSame('Нет', $rows[0][1]->text);
    }

    public function testButtonTextsDependOnConfig(): void
    {
        config()->set('filament-max-broadcasts.consent.button_text_opt_in', 'Yes');
        config()->set('filament-max-broadcasts.consent.button_text_opt_out', 'No');

        $buttons = ConsentButtons::forSegment(1)[0];

        self::assertSame('Yes', $buttons[0]->text);
        self::assertSame('No', $buttons[1]->text);
    }
}
