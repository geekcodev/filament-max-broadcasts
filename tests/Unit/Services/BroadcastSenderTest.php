<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\Promo;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastSender;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\OffersType;
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
            [],
            News::News,
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
            [
                ['upload_type' => 'image', 'path' => 'broadcasts/photo.jpg'],
            ],
            News::News,
        );

        self::assertSame(3, $http->callCount);

        $firstUri = (string) $http->requests[0]->getUri();
        self::assertStringContainsString('/uploads', $firstUri);
        self::assertStringContainsString('type=image', $firstUri);

        $body = $this->decodeBody($http);

        $attachments = $this->attachments($body);
        self::assertCount(1, $attachments);
        self::assertSame('image', $attachments[0]['type'] ?? null);
        self::assertSame('tok-123', $attachments[0]['payload']['token'] ?? null);
    }

    public function testSendTypeWithConfiguredButtonsAddsInlineKeyboard(): void
    {
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.buttons.per_type.promo', [
            ['text' => 'Book', 'startapp' => 'booking'],
        ]);

        [$http, $sender] = $this->makeSender([$this->messageResponse(111)]);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Promo',
            [],
            Promo::Promo,
        );

        self::assertSame(1, $http->callCount);

        $body = $this->decodeBody($http);

        $attachments = $this->attachments($body);
        self::assertCount(1, $attachments);
        self::assertSame('inline_keyboard', $attachments[0]['type'] ?? null);
    }

    public function testSendImageAndPromo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/img.jpg', 'fake-image');
        config()->set('filament-max-broadcasts.image.disk', 'public');
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.buttons.per_type.promo', [
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
            [
                ['upload_type' => 'image', 'path' => 'banners/img.jpg'],
            ],
            Promo::Promo,
        );

        $body = $this->decodeBody($http);

        $attachments = $this->attachments($body);
        self::assertCount(2, $attachments);
        self::assertSame('image', $attachments[0]['type'] ?? null);
        self::assertSame('inline_keyboard', $attachments[1]['type'] ?? null);
    }

    public function testSendMultipleImageUploadsMedia(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('a/img1.jpg', 'fake-image-1');
        Storage::disk('public')->put('a/img2.jpg', 'fake-image-2');
        config()->set('filament-max-broadcasts.image.disk', 'public');

        [$http, $sender] = $this->makeSender([
            new Response(200, [], json_encode(['url' => 'https://upload.example/1', 'token' => 'step1'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['token' => 'tok-1'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['url' => 'https://upload.example/2', 'token' => 'step2'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['token' => 'tok-2'], JSON_THROW_ON_ERROR)),
            $this->messageResponse(111),
        ]);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Two images',
            [
                ['upload_type' => 'image', 'path' => 'a/img1.jpg'],
                ['upload_type' => 'image', 'path' => 'a/img2.jpg'],
            ],
            News::News,
        );

        $body = $this->decodeBody($http);

        $attachments = $this->attachments($body);
        self::assertCount(2, $attachments);
        self::assertSame('image', $attachments[0]['type'] ?? null);
        self::assertSame('tok-1', $attachments[0]['payload']['token'] ?? null);
        self::assertSame('image', $attachments[1]['type'] ?? null);
        self::assertSame('tok-2', $attachments[1]['payload']['token'] ?? null);
    }

    public function testSendVideoAndFileUploadsMedia(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('v/clip.mp4', 'fake-video');
        Storage::disk('public')->put('f/doc.pdf', 'fake-file');
        config()->set('filament-max-broadcasts.image.disk', 'public');

        [$http, $sender] = $this->makeSender([
            new Response(200, [], json_encode(['url' => 'https://upload.example/clip', 'token' => 's'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['token' => 'tok-v'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['url' => 'https://upload.example/doc', 'token' => 's2'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['token' => 'tok-f'], JSON_THROW_ON_ERROR)),
            $this->messageResponse(111),
        ]);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Video and file',
            [
                ['upload_type' => 'video', 'path' => 'v/clip.mp4'],
                ['upload_type' => 'file', 'path' => 'f/doc.pdf'],
            ],
            News::News,
        );

        $body = $this->decodeBody($http);

        $attachments = $this->attachments($body);
        self::assertCount(2, $attachments);
        self::assertSame('video', $attachments[0]['type'] ?? null);
        self::assertSame('tok-v', $attachments[0]['payload']['token'] ?? null);
        self::assertSame('file', $attachments[1]['type'] ?? null);
        self::assertSame('tok-f', $attachments[1]['payload']['token'] ?? null);
    }

    public function testCustomTypeWithConfiguredButtonsAddsInlineKeyboard(): void
    {
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.buttons.per_type', [
            'offers' => [['text' => 'Buy', 'startapp' => 'shop']],
        ]);

        [$http, $sender] = $this->makeSender([$this->messageResponse(111)]);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Offers',
            [],
            OffersType::Offers,
        );

        $body = $this->decodeBody($http);

        $attachments = $this->attachments($body);
        self::assertCount(1, $attachments);
        self::assertSame('inline_keyboard', $attachments[0]['type'] ?? null);
    }

    public function testSendSkipsInvalidMediaEntries(): void
    {
        config()->set('filament-max-broadcasts.image.disk', 'public');

        [$http, $sender] = $this->makeSender([$this->messageResponse(111)]);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Message',
            [
                ['upload_type' => 'gif', 'path' => 'x.gif'],
                ['upload_type' => 'image', 'path' => ''],
            ],
            News::News,
        );

        self::assertSame(1, $http->callCount);

        $body = $this->decodeBody($http);
        self::assertArrayNotHasKey('attachments', $body);
    }

    public function testSendThrowsWhenMediaFileDoesNotExist(): void
    {
        Storage::fake('public');
        config()->set('filament-max-broadcasts.image.disk', 'public');

        [$http, $sender] = $this->makeSender([$this->messageResponse(111)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Media file not found or not readable');

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Message',
            [
                ['upload_type' => 'image', 'path' => 'broadcasts/missing.jpg'],
            ],
            News::News,
        );
    }

    public function testSendRelogsAndRethrowsWhenUploadFails(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('broadcasts/photo.jpg', 'fake-image');
        config()->set('filament-max-broadcasts.image.disk', 'public');

        [$http, $sender] = $this->makeSender();

        $this->expectException(\Throwable::class);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'Message',
            [
                ['upload_type' => 'image', 'path' => 'broadcasts/photo.jpg'],
            ],
            News::News,
        );
    }

    public function testTypeWithoutButtonsOmitsKeyboard(): void
    {
        config()->set('filament-max-broadcasts.bot_username', 'mybot');
        config()->set('filament-max-broadcasts.buttons.per_type', [
            'promo' => [['text' => 'Book', 'startapp' => 'booking']],
        ]);

        [$http, $sender] = $this->makeSender([$this->messageResponse(111)]);

        $sender->send(
            new Recipient(chatId: 111, userId: 222),
            'News without buttons',
            [],
            News::News,
        );

        $body = $this->decodeBody($http);

        self::assertArrayNotHasKey('attachments', $body);
    }

    /**
     * Декодит JSON-тело последнего HTTP-запроса (сообщение в MAX).
     *
     * @return array<string, mixed>
     */
    private function decodeBody(MockHttpClient $http): array
    {
        $request = $http->lastRequest;
        self::assertNotNull($request);

        $decoded = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $body */
        $body = $decoded;

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array{type?: string, payload?: array{token?: string}}>
     */
    private function attachments(array $body): array
    {
        /** @var list<array{type?: string, payload?: array{token?: string}}> $attachments */
        $attachments = $body['attachments'] ?? [];

        return $attachments;
    }
}
