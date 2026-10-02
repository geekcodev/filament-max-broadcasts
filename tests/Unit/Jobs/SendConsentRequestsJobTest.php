<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Jobs;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\ConsentPoll;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendConsentRequestsJob;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSender;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastTextSanitizer;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentRequestService;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Dto\Recipient;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;

class SendConsentRequestsJobTest extends TestCase
{
    private function activeChat(int $chatId, ?int $userId = null): MaxChat
    {
        return Chats::create($chatId, $userId ?? $chatId, [
            'status' => MaxChatStatus::Active,
        ]);
    }

    private function service(): ConsentRequestService
    {
        return new ConsentRequestService(
            new BroadcastRecipientsResolver(),
            new ConsentService(),
        );
    }

    private function sanitizer(): BroadcastTextSanitizer
    {
        return new BroadcastTextSanitizer();
    }

    public function testHandleSendsOnlyToRecipientsWithoutConsent(): void
    {
        $this->activeChat(11, 1);
        $this->activeChat(22, 2);

        (new ConsentService())->optIn(22, 2);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->once()->with(
            Mockery::on(
                static fn (Recipient $recipient): bool => $recipient->userId === 1 && $recipient->chatId === 11,
            ),
            'Согласны ли вы получать наши новости и акции?',
            [],
            ConsentPoll::ConsentPoll,
        );

        (new SendConsentRequestsJob())->handle($sender, $this->service(), $this->sanitizer());
    }

    public function testHandleSendsToAllEligible(): void
    {
        $this->activeChat(11, 1);
        $this->activeChat(22, 2);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->twice();

        (new SendConsentRequestsJob())->handle($sender, $this->service(), $this->sanitizer());
    }

    public function testHandleDoesNothingWhenEveryoneAnswered(): void
    {
        $this->activeChat(11, 1);

        (new ConsentService())->optOut(11, 1);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldNotReceive('send');

        (new SendConsentRequestsJob())->handle($sender, $this->service(), $this->sanitizer());
    }

    public function testHandleSkipsWhenLockIsHeld(): void
    {
        $this->activeChat(11, 1);

        $lock = Cache::lock('consent:send-request', 600);
        self::assertTrue($lock->get());

        try {
            $sender = $this->mock(BroadcastSender::class);
            $sender->shouldNotReceive('send');

            (new SendConsentRequestsJob())->handle($sender, $this->service(), $this->sanitizer());
        } finally {
            $lock->release();
        }
    }

    public function testHandleSwallowsRecipientSendErrors(): void
    {
        $this->activeChat(11, 1);
        $this->activeChat(22, 2);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->once()->andThrow(new RuntimeException('MAX API down'));
        $sender->shouldReceive('send')->once();

        (new SendConsentRequestsJob())->handle($sender, $this->service(), $this->sanitizer());
    }
}
