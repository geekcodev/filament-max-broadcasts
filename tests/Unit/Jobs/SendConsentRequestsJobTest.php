<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Jobs;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendConsentRequestsJob;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
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
use Illuminate\Support\Facades\DB;
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

    /**
     * @param  list<int>  $chatIds
     */
    private function segment(string $name = 'VIP', array $chatIds = []): BroadcastSegment
    {
        return BroadcastSegment::query()->create([
            'name' => $name,
            'chat_ids' => $chatIds,
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
        $segment = $this->segment('VIP', [11, 22]);
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
            null,
            Mockery::type('array'),
        );

        (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer(), $this->consent);

        $pending = BroadcastConsent::query()->where('chat_id', 11)->first();
        self::assertNotNull($pending);
        self::assertSame(BroadcastConsentAction::Pending, $pending->action);
        self::assertSame('request', $pending->source);
        self::assertSame(1, $pending->user_id);

        $answered = BroadcastConsent::query()->where('chat_id', 22)->first();
        self::assertNotNull($answered);
        self::assertSame(BroadcastConsentAction::OptIn, $answered->action);
        self::assertSame('callback', $answered->source);
        self::assertSame(2, BroadcastConsent::query()->count());
    }

    public function testHandleLooksUpSegmentAtMostTwicePerRun(): void
    {
        $this->dialog(11, 1);
        $this->dialog(22, 2);
        $this->dialog(33, 3);
        $segment = $this->segment('VIP', [11, 22, 33]);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->times(3);

        DB::flushQueryLog();
        DB::enableQueryLog();

        (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer(), $this->consent);

        $segmentQueries = array_filter(
            DB::getQueryLog(),
            static fn (array $query): bool => str_contains((string) $query['query'], 'max_broadcast_segments'),
        );

        self::assertLessThanOrEqual(2, count($segmentQueries));
    }

    public function testHandleKeepsSinglePendingRowOnRepeatedRequest(): void
    {
        $this->dialog(11, 1);
        $segment = $this->segment('VIP', [11]);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->twice();

        $job = new SendConsentRequestsJob($segment->id);
        $job->handle($sender, $this->service(), $this->sanitizer(), $this->consent);
        $job->handle($sender, $this->service(), $this->sanitizer(), $this->consent);

        self::assertSame(1, BroadcastConsent::query()->count());

        $pending = BroadcastConsent::query()->first();
        self::assertNotNull($pending);
        self::assertSame(BroadcastConsentAction::Pending, $pending->action);
    }

    public function testHandleSendsButtonsWithSegmentPayloads(): void
    {
        $this->dialog(11, 1);
        $segment = $this->segment('VIP', [11]);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->once()->with(
            Mockery::any(),
            Mockery::any(),
            [],
            null,
            Mockery::on(function (array $rows) use ($segment): bool {
                /** @var list<\GeekCo\MaxPhpClient\Dto\Button\CallbackButton> $buttons */
                $buttons = $rows[0];

                return $buttons[0]->payload === 'consent:'.$segment->id.':opt_in'
                    && $buttons[1]->payload === 'consent:'.$segment->id.':opt_out';
            }),
        );

        (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer(), $this->consent);
    }

    public function testHandleDoesNothingWhenEveryoneAnswered(): void
    {
        $this->dialog(11, 1);
        $segment = $this->segment('VIP', [11]);
        $this->consent->optOut(11, 1, $segment->id);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldNotReceive('send');

        (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer(), $this->consent);
    }

    public function testHandleDoesNothingForUnknownSegment(): void
    {
        $this->dialog(11, 1);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldNotReceive('send');

        (new SendConsentRequestsJob(999))->handle($sender, $this->service(), $this->sanitizer(), $this->consent);
    }

    public function testHandleSkipsWhenLockIsHeldForSameSegment(): void
    {
        $this->dialog(11, 1);
        $segment = $this->segment('VIP', [11]);

        $lock = Cache::lock('consent:send-request:'.$segment->id, 600);
        self::assertTrue($lock->get());

        try {
            $sender = $this->mock(BroadcastSender::class);
            $sender->shouldNotReceive('send');

            (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer(), $this->consent);
        } finally {
            $lock->release();
        }
    }

    public function testHandleKeepsSendingWhenJournalWriteFails(): void
    {
        $this->dialog(11, 1);
        $segment = $this->segment('VIP', [11]);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')->once();

        $consents = $this->mock(ConsentService::class);
        $consents->shouldReceive('requestSentForSegment')->once()->andThrow(new RuntimeException('journal down'));

        (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer(), $consents);

        self::assertSame(0, BroadcastConsent::query()->count());
    }

    public function testHandleSwallowsRecipientSendErrors(): void
    {
        $this->dialog(11, 1);
        $this->dialog(22, 2);
        $segment = $this->segment('VIP', [11, 22]);

        $sender = $this->mock(BroadcastSender::class);
        $sender->shouldReceive('send')
            ->twice()
            ->andThrow(new RuntimeException('MAX down'));

        (new SendConsentRequestsJob($segment->id))->handle($sender, $this->service(), $this->sanitizer(), $this->consent);

        self::assertSame(0, BroadcastConsent::query()->count());
    }

    public function testFailedLogsErrorWithoutTouchingSegment(): void
    {
        $segment = $this->segment();

        (new SendConsentRequestsJob($segment->id))->failed(new RuntimeException('boom'));

        self::assertTrue(BroadcastSegment::query()->whereKey($segment->getKey())->exists());
    }
}
