<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastType;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSender;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\FilamentMaxBroadcasts\Tests\Support\MockHttpClient;
use GeekCo\MaxPhpClient\ApiClient;
use GeekCo\MaxPhpClient\Dto\Recipient;
use GeekCo\MaxPhpClient\RateLimit\RateLimiter;
use GeekCo\MaxPhpClient\Retry\RetryStrategy;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

class BroadcastSenderTest extends TestCase
{
    /**
     * @param  list<Response>  $responses
     * @return array{MockHttpClient, BroadcastSender}
     */
    private function makeSender(array $responses = []): array
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

        return [$http, new BroadcastSender($api)];
    }

    private function messageResponse(int $chatId): Response
    {
        return new Response(200, [], json_encode([
            'message' => [
                'recipient' => ['chat_id' => $chatId],
                'timestamp' => 1700000000,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function testSendSimpleTextWithoutAttachments(): void
    {
        [$http, $sender] = $this->makeSender([$this->messageResponse(111)]);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Hello <b>world</b>',
            null,
            BroadcastType::News,
        );

        self::assertSame(1, $http->callCount);
        self::assertSame('POST', $http->lastRequest?->getMethod());

        $uri = (string) $http->requests[0]->getUri();
        self::assertStringContainsString('chat_id=111', $uri);
        self::assertStringContainsString('user_id=222', $uri);

        $body = $this->decodeBody($http);

        self::assertSame('Hello <b>world</b>', $body['text'] ?? null);
        self::assertSame('html', $body['format'] ?? null);
        self::assertArrayNotHasKey('attachments', $body);
    }

    public function testSendWithImageUploadsMedia(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('broadcasts/photo.jpg', 'fake-image');
        config()->set('filament-max-broadcasts.image.disk', 'public');

        [$http, $sender] = $this->makeSender([
            new Response(200, [], json_encode(['url' => 'https://upload.example/photo', 'token' => 'step1'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['token' => 'tok-123'], JSON_THROW_ON_ERROR)),
            $this->messageResponse(111),
        ]);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Hello',
            'broadcasts/photo.jpg',
            BroadcastType::News,
        );

        self::assertSame(3, $http->callCount);

        $firstUri = (string) $http->requests[0]->getUri();
        self::assertStringContainsString('/uploads', $firstUri);
        self::assertStringContainsString('type=image', $firstUri);

        $body = $this->decodeBody($http);

        $attachments = $body['attachments'] ?? [];
        self::assertCount(1, $attachments);
        self::assertSame('image', $attachments[0]['type'] ?? null);
        self::assertSame('tok-123', $attachments[0]['payload']['token'] ?? null);
    }

    public function testSendPromoAddsInlineKeyboard(): void
    {
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.promo_buttons', [
            ['text' => 'Book', 'startapp' => 'booking'],
        ]);

        [$http, $sender] = $this->makeSender([$this->messageResponse(111)]);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Promo',
            null,
            BroadcastType::Promo,
        );

        self::assertSame(1, $http->callCount);

        $body = $this->decodeBody($http);

        $attachments = $body['attachments'] ?? [];
        self::assertCount(1, $attachments);
        self::assertSame('inline_keyboard', $attachments[0]['type'] ?? null);

        $buttons = $attachments[0]['payload']['buttons'][0][0] ?? [];
        self::assertSame('link', $buttons['type'] ?? null);
        self::assertSame('Book', $buttons['text'] ?? null);
        self::assertSame('https://max.ru/mybot?startapp=booking', $buttons['url'] ?? null);
    }

    public function testSendImageAndPromo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/img.jpg', 'fake-image');
        config()->set('filament-max-broadcasts.image.disk', 'public');
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.promo_buttons', [
            ['text' => 'Book', 'startapp' => 'booking'],
        ]);

        [$http, $sender] = $this->makeSender([
            new Response(200, [], json_encode(['url' => 'https://upload.example/img'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['token' => 'tok-img'], JSON_THROW_ON_ERROR)),
            $this->messageResponse(111),
        ]);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Promo with photo',
            'banners/img.jpg',
            BroadcastType::Promo,
        );

        $body = $this->decodeBody($http);

        $attachments = $body['attachments'] ?? [];
        self::assertCount(2, $attachments);
        self::assertSame('image', $attachments[0]['type'] ?? null);
        self::assertSame('inline_keyboard', $attachments[1]['type'] ?? null);
    }

    /**
     * Декодит JSON-тело последнего HTTP-запроса (сообщение в MAX).
     *
     * @return array<mixed>
     */
    private function decodeBody(MockHttpClient $http): array
    {
        $request = $http->lastRequest;
        self::assertNotNull($request);

        $json = (string) $request->getBody();

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
