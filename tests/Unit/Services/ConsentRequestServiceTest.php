<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Jobs\SendConsentRequestsJob;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentRequestService;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Support\Facades\Queue;

class ConsentRequestServiceTest extends TestCase
{
    private ConsentRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ConsentRequestService(
            new BroadcastRecipientsResolver(),
            new ConsentService(),
        );
    }

    private function activeChat(int $chatId, ?int $userId = null): MaxChat
    {
        return MaxChat::query()->create([
            'user_id' => $userId ?? $chatId,
            'chat_id' => $chatId,
            'status' => MaxChatStatus::Active,
        ]);
    }

    public function testEligibleChatsExcludeAnsweredChats(): void
    {
        $this->activeChat(11, 1);
        $this->activeChat(22, 2);
        $this->activeChat(33, 3);

        $service = new ConsentService();
        $service->optIn(22, 2);
        $service->optOut(33, 3);

        $eligible = $this->service->eligibleChats();

        self::assertCount(1, $eligible);
        self::assertSame(11, $eligible->first()?->getAttribute('chat_id'));
    }

    public function testEligibleChatIdsIsEmptyWhenEveryoneAnswered(): void
    {
        $this->activeChat(11, 1);

        (new ConsentService())->optIn(11, 1);

        self::assertSame([], $this->service->eligibleChatIds());
    }

    public function testEligibleChatIdsReturnsWaitingChats(): void
    {
        $this->activeChat(11, 1);
        $this->activeChat(22, 2);

        (new ConsentService())->optOut(11, 1);

        self::assertSame([22], $this->service->eligibleChatIds());
    }

    public function testSendRequestDispatchesJobAndReturnsCount(): void
    {
        $this->activeChat(11, 1);
        $this->activeChat(22, 2);

        self::assertSame(2, $this->service->sendRequest());

        Queue::assertPushed(SendConsentRequestsJob::class);
    }

    public function testSendRequestDoesNotDispatchWhenNoEligible(): void
    {
        $this->activeChat(11, 1);

        (new ConsentService())->optIn(11, 1);

        self::assertSame(0, $this->service->sendRequest());

        Queue::assertNotPushed(SendConsentRequestsJob::class);
    }
}
