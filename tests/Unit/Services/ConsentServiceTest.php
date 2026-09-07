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

    public function testOptInAddsChatToSegmentAndRecordsConsent(): void
    {
        $this->service->optIn(111, userId: 222);

        $segment = $this->service->resolveConsentSegment();

        self::assertSame([111], $segment->chat_ids);
        self::assertSame([111], $this->service->consentChatIds());

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

    public function testOptOutRemovesChatAndRecordsAction(): void
    {
        $this->service->optIn(111, userId: 222);
        $this->service->optIn(333);

        $this->service->optOut(111, userId: 999);

        self::assertSame([333], $this->service->consentChatIds());

        $consent = BroadcastConsent::query()->where('chat_id', 111)->first();
        self::assertNotNull($consent);
        self::assertSame(BroadcastConsentAction::OptOut, $consent->action);
        self::assertSame(999, $consent->user_id);
    }

    public function testOptOutWithoutConsentDoesNotAddChat(): void
    {
        $this->service->optOut(555);

        self::assertSame([], $this->service->consentChatIds());
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
            ])->getKey(),
        ]);

        self::assertSame([111], $this->service->answeredChatIds());
    }
}
