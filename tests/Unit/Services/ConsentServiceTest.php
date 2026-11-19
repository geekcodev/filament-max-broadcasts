<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\OffersSegment;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class ConsentServiceTest extends TestCase
{
    private ConsentService $service;

    private BroadcastSegment $segment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ConsentService();
        $this->segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [],
        ]);
    }

    public function testFindSegmentUsesConfiguredSegmentModel(): void
    {
        config()->set('filament-max-broadcasts.segment_model', OffersSegment::class);

        $segment = OffersSegment::query()->create(['name' => 'Offers', 'chat_ids' => []]);

        self::assertInstanceOf(OffersSegment::class, $this->service->findSegment($segment->id));
    }

    public function testFindSegmentReturnsNullForUnknownId(): void
    {
        self::assertNull($this->service->findSegment(999));
        self::assertNotNull($this->service->findSegment($this->segment->id));
    }

    public function testOptInRecordsConsentWithoutChangingSegmentMembership(): void
    {
        $this->service->optIn(111, 222, $this->segment->id);

        self::assertSame([], $this->segment->refresh()->chat_ids);
        self::assertSame([111], $this->service->optedInChatIds($this->segment->id));

        $consent = BroadcastConsent::query()->first();
        self::assertNotNull($consent);
        self::assertSame(111, $consent->chat_id);
        self::assertSame(222, $consent->user_id);
        self::assertSame(BroadcastConsentAction::OptIn, $consent->action);
        self::assertSame($this->segment->id, $consent->segment_id);
    }

    public function testOptInIsIdempotentPerChat(): void
    {
        $this->service->optIn(111, null, $this->segment->id);
        $this->service->optIn(111, null, $this->segment->id);

        self::assertSame([111], $this->service->optedInChatIds($this->segment->id));
        self::assertSame(1, BroadcastConsent::query()->count());
    }

    public function testOptOutKeepsSegmentMembershipAndExcludesFromConsented(): void
    {
        $this->service->optIn(111, 222, $this->segment->id);
        $this->service->optIn(333, null, $this->segment->id);

        $this->service->optOut(111, 999, $this->segment->id);

        self::assertSame([333], $this->service->optedInChatIds($this->segment->id));
        self::assertEqualsCanonicalizing([111, 333], $this->service->answeredChatIds($this->segment->id));

        $consent = BroadcastConsent::query()->where('chat_id', 111)->first();
        self::assertNotNull($consent);
        self::assertSame(BroadcastConsentAction::OptOut, $consent->action);
        self::assertSame(999, $consent->user_id);
    }

    public function testOptOutWithoutConsentDoesNotMakeChatConsented(): void
    {
        $this->service->optOut(555, null, $this->segment->id);

        self::assertSame([], $this->service->optedInChatIds($this->segment->id));
        self::assertSame([555], $this->service->answeredChatIds($this->segment->id));
    }

    public function testAnsweredChatIdsReturnsAllRespondentsRegardlessOfAction(): void
    {
        $this->service->optIn(111, 222, $this->segment->id);
        $this->service->optOut(333, null, $this->segment->id);

        self::assertEqualsCanonicalizing([111, 333], $this->service->answeredChatIds($this->segment->id));
    }

    public function testAnsweredChatIdsIsEmptyBeforeAnyResponse(): void
    {
        self::assertSame([], $this->service->answeredChatIds($this->segment->id));
    }

    public function testConsentRecordsAreScopedToSegment(): void
    {
        $other = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        $this->service->optIn(111, 222, $this->segment->id);

        self::assertSame([111], $this->service->optedInChatIds($this->segment->id));
        self::assertSame([], $this->service->optedInChatIds($other->id));
        self::assertSame([], $this->service->answeredChatIds($other->id));
    }

    public function testOptedInChatIdsBySegmentGroupsConsentInOneQuery(): void
    {
        $news = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        $this->service->optIn(111, null, $this->segment->id);
        $this->service->optOut(222, null, $this->segment->id);
        $this->service->optIn(333, null, $news->id);

        self::assertSame([
            $this->segment->id => [111],
            $news->id => [333],
        ], $this->service->optedInChatIdsBySegment([$this->segment->id, $news->id]));
    }

    public function testUnknownSegmentRecordsNothing(): void
    {
        $this->service->optIn(111, 222, 999);

        self::assertSame(0, BroadcastConsent::query()->count());
    }
}
