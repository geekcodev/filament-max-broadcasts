<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Jobs\SendConsentRequestsJob;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentRequestService;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Support\Facades\Queue;

class ConsentRequestServiceTest extends TestCase
{
    private ConsentRequestService $service;

    private ConsentService $consent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consent = new ConsentService();
        $this->service = new ConsentRequestService(
            new BroadcastRecipientsResolver(),
            $this->consent,
        );
    }

    private function dialog(int $chatId, int $userId): void
    {
        Chats::create($chatId, $userId, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Dialog,
        ]);
    }

    private function segment(string $name = 'VIP'): BroadcastSegment
    {
        return BroadcastSegment::query()->create([
            'name' => $name,
            'chat_ids' => [],
        ]);
    }

    public function testEligibleChatsAreActiveDialogsWithoutAnswerForSegment(): void
    {
        $this->dialog(11, 1);
        $this->dialog(22, 2);
        $segment = $this->segment();

        $this->consent->optIn(22, 2, $segment->id);

        self::assertSame([11], $this->service->eligibleChatIds($segment));
    }

    public function testAnswersToOtherSegmentsDoNotExcludeChats(): void
    {
        $this->dialog(11, 1);
        $this->dialog(22, 2);
        $vip = $this->segment('VIP');
        $news = $this->segment('Новости');

        $this->consent->optIn(22, 2, $vip->id);

        self::assertEqualsCanonicalizing([11, 22], $this->service->eligibleChatIds($news));
    }

    public function testGroupsAndChannelsAreNotEligible(): void
    {
        Chats::create(33, 33, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);
        Chats::create(44, 44, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Channel]);

        self::assertSame([], $this->service->eligibleChatIds($this->segment()));
    }

    public function testEligibleChatIdsIsEmptyWhenEveryoneAnswered(): void
    {
        $this->dialog(11, 1);
        $segment = $this->segment();

        $this->consent->optIn(11, 1, $segment->id);

        self::assertSame([], $this->service->eligibleChatIds($segment));
    }

    public function testSendRequestDispatchesJobWithSegmentId(): void
    {
        Queue::fake();
        $this->dialog(11, 1);
        $this->dialog(22, 2);
        $segment = $this->segment();

        self::assertSame(2, $this->service->sendRequest($segment));

        Queue::assertPushed(
            SendConsentRequestsJob::class,
            static fn (SendConsentRequestsJob $job): bool => $job->segmentId === $segment->id,
        );
    }

    public function testSendRequestDoesNotDispatchWhenNoEligible(): void
    {
        Queue::fake();
        $this->dialog(11, 1);
        $segment = $this->segment();

        $this->consent->optIn(11, 1, $segment->id);

        self::assertSame(0, $this->service->sendRequest($segment));

        Queue::assertNotPushed(SendConsentRequestsJob::class);
    }
}
