<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Support\SegmentConsentRule;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Support\Facades\Validator;

class SegmentConsentRuleTest extends TestCase
{
    private function segment(int $chatId): BroadcastSegment
    {
        Chats::create($chatId, $chatId, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Dialog,
        ]);

        return BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [$chatId],
        ]);
    }

    public function testPassesWithoutSegmentsValue(): void
    {
        $validator = Validator::make(['segment_ids' => null], ['segment_ids' => [new SegmentConsentRule()]]);

        self::assertTrue($validator->passes());
    }

    public function testRejectsSegmentsWithoutConsentedRecipients(): void
    {
        $segment = $this->segment(11);

        $validator = Validator::make(
            ['segment_ids' => [$segment->id]],
            ['segment_ids' => [new SegmentConsentRule()]],
        );

        self::assertTrue($validator->fails());
        self::assertSame(
            __('filament-max-broadcasts::broadcasts.form.segments_no_consent'),
            $validator->errors()->first('segment_ids'),
        );
    }

    public function testAcceptsSegmentsWithConsentedRecipients(): void
    {
        $segment = $this->segment(11);
        app(ConsentService::class)->optIn(11, 11, $segment->id);

        $validator = Validator::make(
            ['segment_ids' => [$segment->id]],
            ['segment_ids' => [new SegmentConsentRule()]],
        );

        self::assertTrue($validator->passes());
    }
}
