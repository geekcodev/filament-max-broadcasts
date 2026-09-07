<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Enums;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class BroadcastConsentActionTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame(['opt_in', 'opt_out'], $this->caseValues());
    }

    /**
     * @return list<string>
     */
    private function caseValues(): array
    {
        $values = [];

        foreach (BroadcastConsentAction::cases() as $case) {
            $values[] = $case->value;
        }

        return $values;
    }

    public function testLabel(): void
    {
        app()->setLocale('ru');

        self::assertSame('Согласен', BroadcastConsentAction::OptIn->label());
        self::assertSame('Не согласен', BroadcastConsentAction::OptOut->label());
    }

    public function testEnglishLabel(): void
    {
        app()->setLocale('en');

        self::assertSame('Agree', BroadcastConsentAction::OptIn->label());
        self::assertSame('Disagree', BroadcastConsentAction::OptOut->label());
    }
}
