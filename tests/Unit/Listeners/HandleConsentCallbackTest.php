<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Listeners;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Listeners\HandleConsentCallback;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Tests\Support\MockHttpClient;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Webhook\MaxUpdateReceived;
use GeekCo\MaxPhpClient\ApiClient;
use GeekCo\MaxPhpClient\Dto\Update;
use GeekCo\MaxPhpClient\RateLimit\RateLimiter;
use GeekCo\MaxPhpClient\Retry\RetryStrategy;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

class HandleConsentCallbackTest extends TestCase
{
    private ConsentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ConsentService();
    }

    /**
     * @param  list<Response>  $responses
     *
     * @return array{MockHttpClient, ApiClient, HandleConsentCallback}
     */
    private function makeListener(array $responses = []): array
    {
        $http = new MockHttpClient($responses);
        $factory = new HttpFactory();

        $api = ApiClient::create(
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory,
            uriFactory: $factory,
            accessToken: 'test-token',
            retryStrategy: new RetryStrategy(maxAttempts: 1, baseDelaySeconds: 0.0),
            rateLimiter: new RateLimiter(tokensPerSecond: 1000, maxTokens: 1000),
            globalRateLimiter: new RateLimiter(tokensPerSecond: 1000, maxTokens: 1000),
        );

        return [$http, $api, new HandleConsentCallback($api, $this->service)];
    }

    private function successResponse(): Response
    {
        return new Response(200, [], json_encode(['success' => true], JSON_THROW_ON_ERROR));
    }

    private function errorResponse(): Response
    {
        return new Response(400, [], json_encode(['code' => 400, 'message' => 'bad request'], JSON_THROW_ON_ERROR));
    }

    private function messageResponse(): Response
    {
        return new Response(200, [], json_encode([
            'message' => [
                'recipient' => ['chat_id' => 111],
                'timestamp' => 1700000000,
                'body' => ['mid' => 'mid-1', 'seq' => 1, 'text' => 'text'],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function makeUpdate(array $data): Update
    {
        return Update::fromArray(['timestamp' => 1700000000, ...$data]);
    }

    private function callbackUpdate(string $payload, int $chatId, ?int $userId = null): Update
    {
        $data = [
            'update_type' => 'message_callback',
            'chat_id' => $chatId,
            'message_id' => 'mid-poll',
            'callback' => [
                'callback_id' => 'cb-1',
                'payload' => $payload,
                'message' => [
                    'recipient' => ['chat_id' => $chatId],
                    'timestamp' => 1700000000,
                    'body' => [
                        'mid' => 'mid-poll',
                        'seq' => 1,
                        'text' => 'Согласны ли вы получать наши новости и акции?',
                        'format' => 'html',
                    ],
                ],
            ],
        ];

        if ($userId !== null) {
            $data['user'] = [
                'user_id' => $userId,
                'first_name' => 'Test',
                'is_bot' => false,
            ];
        }

        return $this->makeUpdate($data);
    }

    public function testIgnoresNonCallbackUpdates(): void
    {
        [$http, , $listener] = $this->makeListener();

        $update = $this->makeUpdate([
            'update_type' => 'message_created',
            'chat_id' => 111,
        ]);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(0, $http->callCount);
        self::assertSame(0, BroadcastConsent::query()->count());
    }

    public function testIgnoresForeignPayload(): void
    {
        [$http, , $listener] = $this->makeListener();

        $update = $this->callbackUpdate('booking:confirm', 111, 222);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(0, $http->callCount);
        self::assertSame(0, BroadcastConsent::query()->count());
    }

    public function testUnknownConsentActionIsIgnored(): void
    {
        [$http, , $listener] = $this->makeListener();

        $update = $this->callbackUpdate('consent:1:subscribed', 111, 222);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(0, $http->callCount);
        self::assertSame(0, BroadcastConsent::query()->count());
    }

    public function testOptInRecordsConsentAndAnswersCallback(): void
    {
        $segment = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        [$http, , $listener] = $this->makeListener([
            $this->successResponse(), // deleteMessage
            $this->messageResponse(), // sendMessage
            $this->successResponse(), // sendAnswer
        ]);

        $update = $this->callbackUpdate('consent:'.$segment->id.':opt_in', 111, 222);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(3, $http->callCount);

        self::assertSame('DELETE', $http->requests[0]->getMethod());
        $deleteUri = (string) $http->requests[0]->getUri();
        self::assertStringContainsString('/messages', $deleteUri);
        self::assertStringContainsString('message_id=mid-poll', $deleteUri);

        self::assertSame('POST', $http->requests[1]->getMethod());
        $sendUri = (string) $http->requests[1]->getUri();
        self::assertStringContainsString('/messages', $sendUri);
        self::assertStringContainsString('chat_id=111', $sendUri);
        $sendBody = json_decode((string) $http->requests[1]->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($sendBody);
        self::assertSame('Вы согласились на получение рассылок.', $sendBody['text'] ?? null);

        self::assertSame('POST', $http->requests[2]->getMethod());
        $answerUri = (string) $http->requests[2]->getUri();
        self::assertStringContainsString('/answers', $answerUri);
        self::assertStringContainsString('callback_id=cb-1', $answerUri);

        self::assertSame([111], $segment->refresh()->chat_ids);

        $consent = BroadcastConsent::query()->first();
        self::assertNotNull($consent);
        self::assertSame(111, $consent->chat_id);
        self::assertSame(222, $consent->user_id);
        self::assertSame(BroadcastConsentAction::OptIn, $consent->action);
        self::assertSame($segment->id, $consent->segment_id);
    }

    public function testOptOutRemovesChatAndAnswersCallback(): void
    {
        $segment = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);
        $this->service->optIn(111, 222, $segment->id);
        $this->service->optIn(333, null, $segment->id);

        [$http, , $listener] = $this->makeListener([
            $this->successResponse(),
            $this->messageResponse(),
            $this->successResponse(),
        ]);

        $update = $this->callbackUpdate('consent:'.$segment->id.':opt_out', 111, 222);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(3, $http->callCount);
        self::assertSame([333], $segment->refresh()->chat_ids);

        self::assertSame('DELETE', $http->requests[0]->getMethod());

        $sendBody = json_decode((string) $http->requests[1]->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($sendBody);
        self::assertSame('Вы отказались от получения рассылок.', $sendBody['text'] ?? null);

        $consent = BroadcastConsent::query()->where('chat_id', 111)->first();
        self::assertNotNull($consent);
        self::assertSame(BroadcastConsentAction::OptOut, $consent->action);
    }

    public function testMessageNotDeletedWhenMessageIdUnavailable(): void
    {
        $segment = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        [$http, , $listener] = $this->makeListener([
            $this->messageResponse(),
            $this->successResponse(),
        ]);

        $update = $this->makeUpdate([
            'update_type' => 'message_callback',
            'chat_id' => 111,
            'message_id' => '',
            'callback' => [
                'callback_id' => 'cb-1',
                'payload' => 'consent:'.$segment->id.':opt_in',
            ],
        ]);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(2, $http->callCount);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertStringContainsString('/messages', (string) $http->requests[0]->getUri());
        self::assertStringNotContainsString('message_id=', (string) $http->requests[0]->getUri());
        self::assertStringContainsString('/answers', (string) $http->requests[1]->getUri());
        self::assertSame([111], $segment->refresh()->chat_ids);
    }

    public function testFallbackIsSkippedWhenDeleteFailsAndMessageTextUnavailable(): void
    {
        $segment = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        [$http, , $listener] = $this->makeListener([
            $this->errorResponse(), // deleteMessage fails
            $this->messageResponse(), // sendMessage
            $this->successResponse(), // sendAnswer
        ]);

        $update = $this->makeUpdate([
            'update_type' => 'message_callback',
            'chat_id' => 111,
            'message_id' => 'mid-poll',
            'callback' => [
                'callback_id' => 'cb-1',
                'payload' => 'consent:'.$segment->id.':opt_in',
            ],
        ]);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(3, $http->callCount);
        self::assertSame('DELETE', $http->requests[0]->getMethod());
        self::assertSame('POST', $http->requests[1]->getMethod());
        self::assertStringContainsString('/answers', (string) $http->requests[2]->getUri());
        self::assertSame([111], $segment->refresh()->chat_ids);
    }

    public function testIgnoresCallbackUpdateWithoutCallbackPayload(): void
    {
        [$http, , $listener] = $this->makeListener();

        $update = $this->makeUpdate([
            'update_type' => 'message_callback',
            'chat_id' => 111,
        ]);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(0, $http->callCount);
        self::assertSame(0, BroadcastConsent::query()->count());
    }

    public function testIgnoresCallbackWithoutChatId(): void
    {
        $segment = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        [$http, , $listener] = $this->makeListener();

        $update = $this->makeUpdate([
            'update_type' => 'message_callback',
            'callback' => [
                'callback_id' => 'cb-1',
                'payload' => 'consent:'.$segment->id.':opt_in',
            ],
        ]);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(0, $http->callCount);
        self::assertSame(0, BroadcastConsent::query()->count());
    }

    public function testConsentIsRecordedEvenWhenDeleteMessageFailsAndFallsBackToEdit(): void
    {
        $segment = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        [$http, , $listener] = $this->makeListener([
            $this->errorResponse(), // deleteMessage fails
            $this->successResponse(), // editMessage fallback
            $this->messageResponse(), // sendMessage
            $this->successResponse(), // sendAnswer
        ]);

        $listener->handle(new MaxUpdateReceived($this->callbackUpdate('consent:'.$segment->id.':opt_in', 111, 222)));

        self::assertSame(4, $http->callCount);

        self::assertSame('DELETE', $http->requests[0]->getMethod());
        self::assertSame('PUT', $http->requests[1]->getMethod());
        $editUri = (string) $http->requests[1]->getUri();
        self::assertStringContainsString('/messages', $editUri);
        self::assertStringContainsString('message_id=mid-poll', $editUri);
        $editBody = json_decode((string) $http->requests[1]->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($editBody);
        self::assertSame('Согласны ли вы получать наши новости и акции?', $editBody['text'] ?? null);
        self::assertArrayNotHasKey('attachments', $editBody);

        $consent = BroadcastConsent::query()->where('chat_id', 111)->first();
        self::assertNotNull($consent);
        self::assertSame(BroadcastConsentAction::OptIn, $consent->action);
        self::assertSame([111], $segment->refresh()->chat_ids);
        self::assertStringContainsString('/messages', (string) $http->requests[2]->getUri());
    }

    public function testConsentIsRecordedEvenWhenConfirmationFails(): void
    {
        $segment = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        [$http, , $listener] = $this->makeListener([
            $this->successResponse(),
            $this->errorResponse(),
            $this->successResponse(),
        ]);

        $listener->handle(new MaxUpdateReceived($this->callbackUpdate('consent:'.$segment->id.':opt_in', 111, 222)));

        self::assertSame(3, $http->callCount);

        $consent = BroadcastConsent::query()->where('chat_id', 111)->first();
        self::assertNotNull($consent);
        self::assertStringContainsString('/answers', (string) $http->requests[2]->getUri());
    }

    public function testConsentIsRecordedEvenWhenCallbackAnswerFails(): void
    {
        $segment = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        [$http, , $listener] = $this->makeListener([
            $this->successResponse(),
            $this->messageResponse(),
            $this->errorResponse(),
        ]);

        $listener->handle(new MaxUpdateReceived($this->callbackUpdate('consent:'.$segment->id.':opt_in', 111, 222)));

        self::assertSame(3, $http->callCount);

        $consent = BroadcastConsent::query()->where('chat_id', 111)->first();
        self::assertNotNull($consent);
        self::assertSame(BroadcastConsentAction::OptIn, $consent->action);
    }

    public function testLegacyPayloadIsIgnored(): void
    {
        [$http, , $listener] = $this->makeListener();

        $listener->handle(new MaxUpdateReceived($this->callbackUpdate('consent:opt_in', 111, 222)));

        self::assertSame(0, $http->callCount);
        self::assertSame(0, BroadcastConsent::query()->count());
    }

    public function testSegmentPayloadRecordsConsentIntoThatSegment(): void
    {
        $vip = BroadcastSegment::query()->create(['name' => 'VIP', 'chat_ids' => []]);
        $news = BroadcastSegment::query()->create(['name' => 'Новости', 'chat_ids' => []]);

        [$http, , $listener] = $this->makeListener([
            $this->successResponse(),
            $this->messageResponse(),
            $this->successResponse(),
        ]);

        $listener->handle(new MaxUpdateReceived($this->callbackUpdate('consent:'.$vip->id.':opt_in', 111, 222)));

        self::assertSame(3, $http->callCount);
        self::assertSame([111], $vip->refresh()->chat_ids);
        self::assertSame([], $news->refresh()->chat_ids);
        self::assertSame(
            $vip->id,
            BroadcastConsent::query()->where('chat_id', 111)->sole()->segment_id,
        );
    }

    public function testSegmentPayloadWithUnknownSegmentIsIgnored(): void
    {
        [$http, , $listener] = $this->makeListener();

        $listener->handle(new MaxUpdateReceived($this->callbackUpdate('consent:999:opt_in', 111, 222)));

        self::assertSame(0, $http->callCount);
        self::assertSame(0, BroadcastConsent::query()->count());
    }
}
