<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Jobs;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\ConsentPoll;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendConsentRequestsJob;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSender;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastTextSanitizer;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentRequestService;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\MaxPhpClient\Dto\Recipient;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;

class SendConsentRequestsJobTest extends TestCase
{
    private ConsentService $consent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consent = new ConsentService();
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

    private function service(): ConsentRequestService
    {
        return new ConsentRequestService(new BroadcastRecipientsResolver(), $this->consent);
    }

    private function sanitizer(): BroadcastTextSanitizer
    {
        return new BroadcastTextSanitizer();
    }

    public function testHandleSendsOnlyToDialogsWithoutAnswerForSegment(): void
    {
        $this->dialog(11, 1);
        $this->dialog(22, 2);
        $segment = $this->segment();
        $this->consent->optIn(22, 2, $segment->id);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->once()->with(
            Mockery::on(
                static fn (Recipient $recipient): bool => $recipient->userId === 1 && $recipient->chatId === 11,
            ),
            Mockery::on(
                static fn (string $text): bool => str_contains($text, '«VIP»'),
            ),
            [],
            ConsentPoll::ConsentPoll,
            Mockery::type('array'),
        );

        (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer());
    }

    public function testHandleSendsButtonsWithSegmentPayloads(): void
    {
        $this->dialog(11, 1);
        $segment = $this->segment();

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->once()->with(
            Mockery::any(),
            Mockery::any(),
            [],
            ConsentPoll::ConsentPoll,
            Mockery::on(function (array $rows) use ($segment): bool {
                /** @var \GeekCo\MaxPhpClient\Dto\InlineKeyboardButtonRow $row */
                $row = $rows[0];

                return $row->buttons[0]->payload === 'consent:'.$segment->id.':opt_in'
                    && $row->buttons[1]->payload === 'consent:'.$segment->id.':opt_out';
            }),
        );

        (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer());
    }

    public function testHandleDoesNothingWhenEveryoneAnswered(): void
    {
        $this->dialog(11, 1);
        $segment = $this->segment();
        $this->consent->optOut(11, 1, $segment->id);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldNotReceive('send');

        (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer());
    }

    public function testHandleDoesNothingForUnknownSegment(): void
    {
        $this->dialog(11, 1);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldNotReceive('send');

        (new SendConsentRequestsJob(999))->handle($sender, $this->service(), $this->sanitizer());
    }

    public function testHandleSkipsWhenLockIsHeldForSameSegment(): void
    {
        $this->dialog(11, 1);
        $segment = $this->segment();

        $lock = Cache::lock('consent:send-request:'.$segment->id, 600);
        self::assertTrue($lock->get());

        try {
            $sender = $this->mock(BroadcastSender::class);
            $sender->shouldNotReceive('send');

            (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer());
        } finally {
            $lock->release();
        }
    }

    public function testHandleSwallowsRecipientSendErrors(): void
    {
        $this->dialog(11, 1);
        $this->dialog(22, 2);
        $segment = $this->segment();

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')
            ->twice()
            ->andThrow(new RuntimeException('MAX down'));

        (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer());

    }
}
