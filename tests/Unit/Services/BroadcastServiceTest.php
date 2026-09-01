<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastType;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendBroadcastJob;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastService;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastTextSanitizer;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Queue;

class BroadcastServiceTest extends TestCase
{
    private function makeResolverWithChats(int ...$pairs): BroadcastRecipientsResolver
    {
        $chats = [];

        foreach (array_chunk($pairs, 2) as [$userId, $chatId]) {
            $chats[] = MaxChat::query()->create([
                'user_id' => $userId,
                'chat_id' => $chatId,
                'status' => MaxChatStatus::Active,
            ]);
        }

        $resolver = $this->mock(BroadcastRecipientsResolver::class);
        $resolver->shouldReceive('resolve')->andReturn(new Collection($chats));

        return $resolver;
    }

    public function testCreateBuildsBroadcastWithRecipientsAndDispatchesJob(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11, 2, 22),
        );

        $broadcast = $service->create(
            text: 'Hello <script>alert(1)</script><b>world</b>',
            scheduledAt: null,
        );

        self::assertSame(BroadcastStatus::Running, $broadcast->status);
        self::assertSame('Hello <b>world</b>', $broadcast->text);
        self::assertSame(2, $broadcast->total_recipients);

        $recipients = $broadcast->recipients;

        self::assertCount(2, $recipients);
        self::assertContains(11, $recipients->pluck('chat_id')->all());
        self::assertContains(22, $recipients->pluck('chat_id')->all());
        self::assertTrue($recipients->every(
            static fn ($recipient): bool => $recipient->status === BroadcastRecipientStatus::Pending,
        ));

        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function testCreateWithFutureScheduleSetsScheduledStatus(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11),
        );

        $broadcast = $service->create(
            text: 'Later',
            scheduledAt: now()->addHour(),
        );

        self::assertSame(BroadcastStatus::Scheduled, $broadcast->status);
        self::assertNotNull($broadcast->scheduled_at);

        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function testCreateStoresCreator(): void
    {
        $user = TestUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);

        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11),
        );

        $broadcast = $service->create(
            text: 'Hello',
            scheduledAt: null,
            creator: $user,
        );

        self::assertSame($user->id, $broadcast->created_by);
        self::assertSame($user->id, $broadcast->creator->id);
    }

    public function testCreateWithImage(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11),
        );

        $broadcast = $service->create(
            text: 'Hello',
            scheduledAt: null,
            imagePath: 'broadcasts/photo.jpg',
        );

        self::assertSame('broadcasts/photo.jpg', $broadcast->image_path);
    }

    public function testCreateWithPromoType(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11),
        );

        $broadcast = $service->create(
            text: 'Promo!',
            scheduledAt: null,
            type: BroadcastType::Promo,
        );

        self::assertSame(BroadcastType::Promo, $broadcast->type);
    }
}
