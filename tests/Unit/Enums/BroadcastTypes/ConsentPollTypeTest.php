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

    public function testButtonRowsAreCallbackButtonsWithConsentPayloads(): void
    {
        config()->set('filament-max-broadcasts.bot_username', 'mybot');

        $rows = ConsentPoll::ConsentPoll->buttonRows();

        self::assertCount(1, $rows);
        self::assertCount(2, $rows[0]->buttons);

        $optIn = $rows[0]->buttons[0];
        $optOut = $rows[0]->buttons[1];

        self::assertSame(ButtonType::Callback, $optIn->type);
        self::assertSame('Согласен', $optIn->text);
        self::assertSame('consent:opt_in', $optIn->payload);
        self::assertNull($optIn->url);

        self::assertSame(ButtonType::Callback, $optOut->type);
        self::assertSame('Не согласен', $optOut->text);
        self::assertSame('consent:opt_out', $optOut->payload);
        self::assertNull($optOut->url);
    }

    public function testButtonRowsDependOnConfigTexts(): void
    {
        config()->set('filament-max-broadcasts.consent.button_text_opt_in', 'Yes');
        config()->set('filament-max-broadcasts.consent.button_text_opt_out', 'No');

        $buttons = ConsentPoll::ConsentPoll->buttonRows()[0]->buttons;

        self::assertSame('Yes', $buttons[0]->text);
        self::assertSame('No', $buttons[1]->text);
    }
}
