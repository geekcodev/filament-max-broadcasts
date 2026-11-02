<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Enums;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\ConsentPoll;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\MaxPhpClient\Enum\ButtonType;

class ConsentPollTypeTest extends TestCase
{
    public function testValueAndBadgeColor(): void
    {
        self::assertSame(['consent'], $this->caseValues());
        self::assertSame('success', ConsentPoll::ConsentPoll->badgeColor());
    }

    /**
     * @return list<string>
     */
    private function caseValues(): array
    {
        $values = [];

        foreach (ConsentPoll::cases() as $case) {
            $values[] = $case->value;
        }

        return $values;
    }

    public function testLabel(): void
    {
        app()->setLocale('ru');
        self::assertSame('Опрос согласия', ConsentPoll::ConsentPoll->label());

        app()->setLocale('en');
        self::assertSame('Consent poll', ConsentPoll::ConsentPoll->label());
    }

    public function testButtonRowsAreEmptyWithoutSegment(): void
    {
        self::assertSame([], ConsentPoll::ConsentPoll->buttonRows());
    }

    public function testPayloadContainsSegmentAndAction(): void
    {
        self::assertSame('consent:7:opt_in', ConsentPoll::payload(7, 'opt_in'));
        self::assertSame('consent:7:opt_out', ConsentPoll::payload(7, 'opt_out'));

        config()->set('filament-max-broadcasts.consent.payload_prefix', 'vote');

        self::assertSame('vote:7:opt_in', ConsentPoll::payload(7, 'opt_in'));
    }

    public function testButtonRowsForSegmentCarrySegmentIdInPayload(): void
    {
        $rows = ConsentPoll::buttonRowsFor(7);

        self::assertCount(1, $rows);
        self::assertSame('consent:7:opt_in', $rows[0]->buttons[0]->payload);
        self::assertSame('consent:7:opt_out', $rows[0]->buttons[1]->payload);
        self::assertSame('Да', $rows[0]->buttons[0]->text);
        self::assertSame('Нет', $rows[0]->buttons[1]->text);
        self::assertSame(ButtonType::Callback, $rows[0]->buttons[0]->type);
        self::assertNull($rows[0]->buttons[0]->url);
    }

    public function testButtonRowsDependOnConfigTexts(): void
    {
        config()->set('filament-max-broadcasts.consent.button_text_opt_in', 'Yes');
        config()->set('filament-max-broadcasts.consent.button_text_opt_out', 'No');

        $buttons = ConsentPoll::buttonRowsFor(1)[0]->buttons;

        self::assertSame('Yes', $buttons[0]->text);
        self::assertSame('No', $buttons[1]->text);
    }
}
