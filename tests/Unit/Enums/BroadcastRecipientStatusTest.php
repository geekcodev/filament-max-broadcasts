<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Enums;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class BroadcastRecipientStatusTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame('pending', BroadcastRecipientStatus::Pending->value);
        self::assertSame('sent', BroadcastRecipientStatus::Sent->value);
        self::assertSame('failed', BroadcastRecipientStatus::Failed->value);
    }

    public function testLabel(): void
    {
        app()->setLocale('en');

        self::assertSame('Pending', BroadcastRecipientStatus::Pending->label());
        self::assertSame('Sent', BroadcastRecipientStatus::Sent->label());
        self::assertSame('Failed', BroadcastRecipientStatus::Failed->label());
    }

    public function testRussianLabels(): void
    {
        app()->setLocale('ru');

        self::assertSame('Ожидает', BroadcastRecipientStatus::Pending->label());
        self::assertSame('Отправлено', BroadcastRecipientStatus::Sent->label());
        self::assertSame('Ошибка', BroadcastRecipientStatus::Failed->label());
    }

    public function testLabels(): void
    {
        app()->setLocale('en');

        self::assertSame([
            'pending' => 'Pending',
            'sent' => 'Sent',
            'failed' => 'Failed',
        ], BroadcastRecipientStatus::labels());
    }
}
