<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Models;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class BroadcastConsentTest extends TestCase
{
    public function testCreatesConsentWithCastsAndDefaultSource(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'Consent',
            'chat_ids' => [],
        ]);

        $consent = BroadcastConsent::query()->create([
            'segment_id' => $segment->id,
            'chat_id' => 111,
            'user_id' => 222,
            'action' => BroadcastConsentAction::OptIn,
        ]);

        self::assertSame(111, $consent->chat_id);
        self::assertSame(222, $consent->user_id);
        self::assertSame(BroadcastConsentAction::OptIn, $consent->action);
        self::assertSame('callback', $consent->source);
    }

    public function testSegmentRelation(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'Consent',
            'chat_ids' => [111],
        ]);

        $consent = BroadcastConsent::query()->create([
            'segment_id' => $segment->id,
            'chat_id' => 111,
            'action' => BroadcastConsentAction::OptIn,
        ]);

        self::assertTrue($consent->segment->is($segment));
    }
}
