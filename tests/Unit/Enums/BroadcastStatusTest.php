<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Enums;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class BroadcastStatusTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame('scheduled', BroadcastStatus::Scheduled->value);
        self::assertSame('running', BroadcastStatus::Running->value);
        self::assertSame('completed', BroadcastStatus::Completed->value);
        self::assertSame('cancelled', BroadcastStatus::Cancelled->value);
        self::assertSame('failed', BroadcastStatus::Failed->value);
    }

    public function testLabelUsesTranslations(): void
    {
        app()->setLocale('en');

        self::assertSame('Scheduled', BroadcastStatus::Scheduled->label());
        self::assertSame('Running', BroadcastStatus::Running->label());
        self::assertSame('Completed', BroadcastStatus::Completed->label());
        self::assertSame('Cancelled', BroadcastStatus::Cancelled->label());
        self::assertSame('Failed', BroadcastStatus::Failed->label());
    }

    public function testLabelUsesRussianTranslations(): void
    {
        app()->setLocale('ru');

        self::assertSame('Запланирована', BroadcastStatus::Scheduled->label());
        self::assertSame('Выполняется', BroadcastStatus::Running->label());
        self::assertSame('Завершена', BroadcastStatus::Completed->label());
        self::assertSame('Отменена', BroadcastStatus::Cancelled->label());
        self::assertSame('Ошибка', BroadcastStatus::Failed->label());
    }

    public function testLabels(): void
    {
        app()->setLocale('en');

        self::assertSame([
            'scheduled' => 'Scheduled',
            'running' => 'Running',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'failed' => 'Failed',
        ], BroadcastStatus::labels());
    }
}
