<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendBroadcastJob;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastAttachment;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastService;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastTextSanitizer;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;

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
        self::assertNotNull($broadcast->creator);

        /** @var TestUser $creator */
        $creator = $broadcast->creator;
        self::assertSame($user->id, $creator->id);
    }

    public function testCreateWithAttachments(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11),
        );

        $broadcast = $service->create(
            text: 'Hello',
            scheduledAt: null,
            attachments: [
                ['upload_type' => 'image', 'path' => 'broadcasts/photo.jpg'],
                ['upload_type' => 'video', 'path' => 'broadcasts/clip.mp4'],
            ],
        );

        self::assertSame(2, $broadcast->attachments()->count());

        $attachments = $broadcast->attachments;
        $first = $attachments->get(0);
        $second = $attachments->get(1);
        self::assertInstanceOf(BroadcastAttachment::class, $first);
        self::assertInstanceOf(BroadcastAttachment::class, $second);
        self::assertSame('image', $first->upload_type->value);
        self::assertSame('broadcasts/photo.jpg', $first->path);
        self::assertSame(0, $first->sort_order);
        self::assertSame('video', $second->upload_type->value);
        self::assertSame('broadcasts/clip.mp4', $second->path);
        self::assertSame(1, $second->sort_order);
    }

    public function testCreateRejectsInvalidAttachmentType(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11),
        );

        $this->expectException(InvalidArgumentException::class);

        $service->create(
            text: 'Hi',
            scheduledAt: null,
            attachments: [
                ['upload_type' => 'gif', 'path' => 'broadcasts/x.gif'],
            ],
        );
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
            type: 'promo',
        );

        self::assertSame('promo', $broadcast->type);
    }

    public function testCreateRejectsUnknownType(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11),
        );

        $this->expectException(InvalidArgumentException::class);

        $service->create(
            text: 'Hi',
            scheduledAt: null,
            type: 'unknown-type',
        );
    }
}
