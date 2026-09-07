<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Listeners;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Listeners\HandleConsentCallback;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
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
            'callback' => [
                'callback_id' => 'cb-1',
                'payload' => $payload,
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

        $update = $this->callbackUpdate('consent:subscribed', 111, 222);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(0, $http->callCount);
        self::assertSame(0, BroadcastConsent::query()->count());
    }

    public function testOptInRecordsConsentAndAnswersCallback(): void
    {
        [$http, , $listener] = $this->makeListener([$this->successResponse()]);

        $update = $this->callbackUpdate('consent:opt_in', 111, 222);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(1, $http->callCount);
        self::assertNotNull($http->lastRequest);
        self::assertSame('POST', $http->lastRequest->getMethod());

        $uri = (string) $http->lastRequest->getUri();
        self::assertStringContainsString('/answers', $uri);
        self::assertStringContainsString('callback_id=cb-1', $uri);

        $body = json_decode((string) $http->lastRequest->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('Спасибо! Ваш ответ учтён.', $body['notification'] ?? null);

        self::assertSame([111], $this->service->consentChatIds());

        $consent = BroadcastConsent::query()->first();
        self::assertNotNull($consent);
        self::assertSame(111, $consent->chat_id);
        self::assertSame(222, $consent->user_id);
        self::assertSame(BroadcastConsentAction::OptIn, $consent->action);
    }

    public function testOptOutRemovesChatAndAnswersCallback(): void
    {
        $this->service->optIn(111, 222);
        $this->service->optIn(333);

        [$http, , $listener] = $this->makeListener([$this->successResponse()]);

        $update = $this->callbackUpdate('consent:opt_out', 111, 222);

        $listener->handle(new MaxUpdateReceived($update));

        self::assertSame(1, $http->callCount);
        self::assertSame([333], $this->service->consentChatIds());

        $consent = BroadcastConsent::query()->where('chat_id', 111)->first();
        self::assertNotNull($consent);
        self::assertSame(BroadcastConsentAction::OptOut, $consent->action);
    }
}
