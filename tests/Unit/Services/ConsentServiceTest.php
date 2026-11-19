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

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ConsentService();
    }

    public function testResolveConsentSegmentCreatesAndReuses(): void
    {
        $segment = $this->service->resolveConsentSegment();

        self::assertSame('Новости и акции', $segment->name);
        self::assertSame([], $segment->chat_ids);
        self::assertSame(1, BroadcastSegment::query()->count());

        self::assertTrue($this->service->resolveConsentSegment()->is($segment));
    }

    public function testResolveConsentSegmentUsesConfiguredSegmentModel(): void
    {
        config()->set('filament-max-broadcasts.segment_model', OffersSegment::class);

        self::assertInstanceOf(OffersSegment::class, $this->service->resolveConsentSegment());
    }

    public function testOptInRecordsConsentWithoutChangingSegmentMembership(): void
    {
        $this->service->optIn(111, userId: 222);

        $segment = $this->service->resolveConsentSegment();

        self::assertSame([], $segment->refresh()->chat_ids);
        self::assertSame([111], $this->service->consentChatIds());
        self::assertSame([111], $this->service->optedInChatIds());

        $consent = BroadcastConsent::query()->first();
        self::assertNotNull($consent);
        self::assertSame(111, $consent->chat_id);
        self::assertSame(222, $consent->user_id);
        self::assertSame(BroadcastConsentAction::OptIn, $consent->action);
    }

    public function testOptInIsIdempotentPerChat(): void
    {
        $this->service->optIn(111);
        $this->service->optIn(111);

        self::assertSame([111], $this->service->consentChatIds());
        self::assertSame(1, BroadcastConsent::query()->count());
    }

    public function testOptOutKeepsSegmentMembershipAndExcludesFromConsented(): void
    {
        $this->service->optIn(111, userId: 222);
        $this->service->optIn(333);

        $this->service->optOut(111, userId: 999);

        self::assertSame([333], $this->service->consentChatIds());
        self::assertEqualsCanonicalizing([111, 333], $this->service->answeredChatIds());

        $consent = BroadcastConsent::query()->where('chat_id', 111)->first();
        self::assertNotNull($consent);
        self::assertSame(BroadcastConsentAction::OptOut, $consent->action);
        self::assertSame(999, $consent->user_id);
    }

    public function testOptOutWithoutConsentDoesNotMakeChatConsented(): void
    {
        $this->service->optOut(555);

        self::assertSame([], $this->service->consentChatIds());
        self::assertSame([555], $this->service->answeredChatIds());
    }

    public function testAnsweredChatIdsReturnsAllRespondentsRegardlessOfAction(): void
    {
        $this->service->optIn(111, 222);
        $this->service->optOut(333);

        self::assertSame([111, 333], $this->service->answeredChatIds());
    }

    public function testAnsweredChatIdsIsEmptyBeforeAnyResponse(): void
    {
        self::assertSame([], $this->service->answeredChatIds());
    }

    public function testAnsweredChatIdsIgnoresRecordsInOtherSegments(): void
    {
        $this->service->optIn(111);

        BroadcastConsent::query()->create([
            'chat_id' => 999,
            'user_id' => null,
            'action' => BroadcastConsentAction::OptIn,
            'source' => 'callback',
            'segment_id' => BroadcastSegment::query()->create([
                'name' => 'Другая рассылка',
                'chat_ids' => [],
            ])->id,
        ]);

        self::assertSame([111], $this->service->answeredChatIds());
        self::assertSame([111], $this->service->optedInChatIds());
    }

    public function testOptInWithSegmentIdRecordsOnlyIntoThatSegment(): void
    {
        $vip = BroadcastSegment::query()->create(['name' => 'VIP', 'chat_ids' => [111]]);
        $news = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => [111]]);

        $this->service->optIn(111, 222, $vip->id);

        self::assertSame([111], $vip->refresh()->chat_ids);
        self::assertSame([111], $news->refresh()->chat_ids);
        self::assertSame([], $this->service->consentChatIds());
        self::assertSame([111], $this->service->optedInChatIds($vip->id));
        self::assertSame([], $this->service->optedInChatIds($news->id));
    }

    public function testOptedInChatIdsBySegmentGroupsConsentInOneQuery(): void
    {
        $vip = BroadcastSegment::query()->create(['name' => 'VIP', 'chat_ids' => []]);
        $news = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        $this->service->optIn(111, null, $vip->id);
        $this->service->optOut(222, null, $vip->id);
        $this->service->optIn(333, null, $news->id);

        self::assertSame([
            $vip->id => [111],
            $news->id => [333],
        ], $this->service->optedInChatIdsBySegment([$vip->id, $news->id]));
    }

    public function testAnsweredChatIdsCanBeReadPerSegment(): void
    {
        $vip = BroadcastSegment::query()->create(['name' => 'VIP', 'chat_ids' => []]);

        $this->service->optOut(111, null, $vip->id);
        $this->service->optIn(222);

        self::assertSame([111], $this->service->answeredChatIds($vip->id));
    }

    public function testFindSegmentReturnsNullForUnknownId(): void
    {
        self::assertNull($this->service->findSegment(999));
        self::assertNotNull($this->service->findSegment(null));
    }
}
