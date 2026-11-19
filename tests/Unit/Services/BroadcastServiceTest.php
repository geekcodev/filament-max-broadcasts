<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendBroadcastJob;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastAttachment;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastService;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Services\BroadcastTextSanitizer;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;

class BroadcastServiceTest extends TestCase
{
    private function makeResolverWithChats(int ...$pairs): BroadcastRecipientsResolver
    {
        $chats = [];

        foreach (array_chunk($pairs, 2) as [$userId, $chatId]) {
            $chats[] = Chats::create($chatId, $userId, [
                'status' => MaxChatStatus::Active,
                'chat_type' => ChatType::Dialog,
            ]);
        }

        return $this->mockResolver($chats);
    }

    private function makeResolverWithGroups(int ...$pairs): BroadcastRecipientsResolver
    {
        $chats = [];

        foreach (array_chunk($pairs, 2) as [$userId, $chatId]) {
            $chats[] = Chats::create($chatId, $userId, [
                'status' => MaxChatStatus::Active,
                'chat_type' => ChatType::Chat,
            ]);
        }

        return $this->mockResolver($chats);
    }

    /**
     * @param  list<MaxChat>  $chats
     */
    private function mockResolver(array $chats): BroadcastRecipientsResolver
    {
        $collection = new Collection($chats);

        $resolver = $this->mock(BroadcastRecipientsResolver::class);
        $resolver->shouldReceive('resolve')->andReturn($collection);
        $resolver->shouldReceive('resolveForChatIds')->andReturnUsing(
            static function (array $ids) use ($collection): Collection {
                $wanted = array_fill_keys(array_map(
                    static fn (mixed $id): int => is_scalar($id) ? (int) $id : 0,
                    $ids,
                ), true);

                return $collection
                    ->filter(static function (MaxChat $chat) use ($wanted): bool {
                        /** @var int $chatId */
                        $chatId = $chat->getAttribute('chat_id');

                        return isset($wanted[$chatId]);
                    })
                    ->values();
            },
        );

        return $resolver;
    }

    public function testCreateBuildsBroadcastWithRecipientsAndDispatchesJob(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithGroups(1, 11, 2, 22),
        );

        $broadcast = $service->create(
            text: 'Hello <script>alert(1)</script><b>world</b>',
            chatIds: [11, 22],
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
            $this->makeResolverWithGroups(1, 11),
        );

        $broadcast = $service->create(
            text: 'Later',
            chatIds: [11, 22],
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
            $this->makeResolverWithGroups(1, 11),
        );

        $broadcast = $service->create(
            text: 'Hello',
            chatIds: [11, 22],
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
            $this->makeResolverWithGroups(1, 11),
        );

        $broadcast = $service->create(
            text: 'Hello',
            chatIds: [11, 22],
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
            chatIds: [11, 22],
            scheduledAt: null,
            attachments: [
                ['upload_type' => 'gif', 'path' => 'broadcasts/x.gif'],
            ],
        );
    }

    public function testCreateWithInvalidAttachmentPersistsNothing(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11),
        );

        try {
            $service->create(
                text: 'Hi',
                chatIds: [11, 22],
                scheduledAt: null,
                attachments: [
                    ['upload_type' => 'gif', 'path' => 'broadcasts/x.gif'],
                ],
            );

            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
        }

        self::assertSame(0, Broadcast::query()->count());
        self::assertSame(0, BroadcastAttachment::query()->count());
    }

    public function testCreateWithPromoType(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithGroups(1, 11),
        );

        $broadcast = $service->create(
            text: 'Promo!',
            chatIds: [11, 22],
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
            chatIds: [11, 22],
            scheduledAt: null,
            type: 'unknown-type',
        );
    }

    public function testCreateWithExplicitChatIdsFiltersRecipients(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithGroups(1, 11, 2, 22, 3, 33),
        );

        $broadcast = $service->create(
            text: 'Selected only',
            scheduledAt: null,
            chatIds: [22, 33],
        );

        self::assertSame(2, $broadcast->total_recipients);
        self::assertSame([22, 33], $broadcast->recipients->pluck('chat_id')->all());
        self::assertSame([22, 33], $broadcast->recipient_chat_ids);
    }

    public function testCreateWithoutSegmentsOrRecipientsIsRejected(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11, 2, 22),
        );

        $this->expectException(InvalidArgumentException::class);

        try {
            $service->create(
                text: 'Nobody',
                scheduledAt: null,
                chatIds: [],
                segments: [],
            );
        } finally {
            self::assertSame(0, Broadcast::query()->count());
        }
    }

    public function testSegmentRecipientsExcludeGroupsAndChannels(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'Mixed',
            'chat_ids' => [11, 33],
        ]);

        $group = Chats::create(33, 3, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Chat,
        ]);

        $consent = new ConsentService();
        $consent->optIn(11, 1, $segment->id);
        $consent->optIn(33, 3, $segment->id);

        $resolver = $this->mockResolver([
            Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]),
            $group,
        ]);

        $service = new BroadcastService(new BroadcastTextSanitizer(), $resolver, $consent);

        $broadcast = $service->create(
            text: 'Dialogs only',
            scheduledAt: null,
            segments: [$segment],
        );

        self::assertSame([11], $broadcast->recipients->pluck('chat_id')->all());
    }

    public function testCreateWithSegmentUsesOnlyConsentedSegmentChats(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11, 22, 33],
        ]);

        $consent = new ConsentService();
        $consent->optIn(11, 1, $segment->id);
        $consent->optIn(22, 2, $segment->id);

        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11, 2, 22, 3, 33),
            $consent,
        );

        $broadcast = $service->create(
            text: 'Segment',
            scheduledAt: null,
            segments: [$segment],
        );

        self::assertSame(2, $broadcast->total_recipients);
        self::assertSame([11, 22], $broadcast->recipients->pluck('chat_id')->all());
        self::assertSame([$segment->id], $broadcast->segments->pluck('id')->all());
        self::assertNull($broadcast->recipient_chat_ids);
    }

    public function testCreateWithSegmentWithoutConsentsIsRejected(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11, 22],
        ]);

        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11, 2, 22),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no active recipients');

        try {
            $service->create(
                text: 'No consent',
                scheduledAt: null,
                segments: [$segment],
            );
        } finally {
            self::assertSame(0, Broadcast::query()->count());
        }
    }

    public function testCreateWithMultipleSegmentsUnionsTheirConsentedChats(): void
    {
        $first = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11, 22],
        ]);
        $second = BroadcastSegment::query()->create([
            'name' => 'Employees',
            'chat_ids' => [22, 33],
        ]);

        $consent = new ConsentService();
        $consent->optIn(11, 1, $first->id);
        $consent->optIn(22, 2, $first->id);
        $consent->optIn(33, 3, $second->id);

        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11, 2, 22, 3, 33),
            $consent,
        );

        $broadcast = $service->create(
            text: 'Union',
            scheduledAt: null,
            segments: [$first, $second],
        );

        self::assertSame(3, $broadcast->total_recipients);
        self::assertSame([11, 22, 33], $broadcast->recipients->pluck('chat_id')->all());
        self::assertSame([$first->id, $second->id], $broadcast->segments->pluck('id')->all());
    }

    public function testRepeatKeepsSamePeopleAndSkipsFreshOptOut(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11, 22],
        ]);

        $consent = new ConsentService();
        $consent->optIn(11, 1, $segment->id);
        $consent->optIn(22, 2, $segment->id);

        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11, 2, 22),
            $consent,
        );

        $original = $service->create(
            text: 'Original',
            scheduledAt: null,
            chatIds: [11, 22],
            segments: [$segment],
        );

        $consent->optOut(11, 1, $segment->id);

        $repeated = $service->repeat($original);

        self::assertSame([22], $repeated->recipients->pluck('chat_id')->all());
        self::assertSame([22], $repeated->recipient_chat_ids);
        self::assertSame([$segment->id], $repeated->segments->pluck('id')->all());
    }

    public function testRepeatKeepsManualGroupsAndFiltersDialogs(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        $consent = new ConsentService();
        $consent->optIn(11, 1, $segment->id);

        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->mockResolver([
                Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]),
                Chats::create(22, 2, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]),
            ]),
            $consent,
        );

        $original = $service->create(
            text: 'Dialog and group',
            scheduledAt: null,
            chatIds: [22],
            segments: [$segment],
        );

        self::assertEqualsCanonicalizing([11, 22], $original->recipient_chat_ids ?? []);

        $consent->optOut(11, 1, $segment->id);

        $repeated = $service->repeat($original);

        self::assertSame([22], $repeated->recipients->pluck('chat_id')->all());
        self::assertSame([22], $repeated->recipient_chat_ids);
    }

    public function testRepeatWithoutSnapshotResolvesCurrentConsent(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11, 22],
        ]);

        $consent = new ConsentService();
        $consent->optIn(11, 1, $segment->id);

        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11, 2, 22),
            $consent,
        );

        $original = $service->create(
            text: 'Segments only',
            scheduledAt: null,
            segments: [$segment],
        );

        self::assertNull($original->recipient_chat_ids);

        $consent->optOut(11, 1, $segment->id);
        $consent->optIn(22, 2, $segment->id);

        $repeated = $service->repeat($original);

        self::assertSame([22], $repeated->recipients->pluck('chat_id')->all());
    }

    public function testRepeatWithoutSegmentsUsesSnapshotAsIs(): void
    {
        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithGroups(1, 11, 2, 22),
            new ConsentService(),
        );

        $original = $service->create(
            text: 'Manual',
            scheduledAt: null,
            chatIds: [11, 22],
        );

        $repeated = $service->repeat($original);

        self::assertSame([11, 22], $repeated->recipients->pluck('chat_id')->all());
    }

    public function testRepeatThrowsWhenEveryoneOptedOut(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        $consent = new ConsentService();
        $consent->optIn(11, 1, $segment->id);

        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11),
            $consent,
        );

        $original = $service->create(
            text: 'Original',
            scheduledAt: null,
            chatIds: [11],
            segments: [$segment],
        );

        $consent->optOut(11, 1, $segment->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no active recipients');

        try {
            $service->repeat($original);
        } finally {
            self::assertSame(1, Broadcast::query()->count());
        }
    }

    public function testExplicitDialogsAreIgnoredForSegmentBroadcast(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11, 22],
        ]);

        $consent = new ConsentService();
        $consent->optIn(11, 1, $segment->id);

        $service = new BroadcastService(
            new BroadcastTextSanitizer(),
            $this->makeResolverWithChats(1, 11, 2, 22),
            $consent,
        );

        $broadcast = $service->create(
            text: 'Only consented dialogs',
            scheduledAt: null,
            chatIds: [22],
            segments: [$segment],
        );

        self::assertSame([11], $broadcast->recipients->pluck('chat_id')->all());
        self::assertSame([11], $broadcast->recipient_chat_ids);
        self::assertSame([$segment->id], $broadcast->segments->pluck('id')->all());
    }

    public function testManualGroupsAreAddedToSegmentBroadcast(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        $consent = new ConsentService();
        $consent->optIn(11, 1, $segment->id);

        $resolver = $this->mockResolver([
            Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]),
            Chats::create(22, 2, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]),
        ]);

        $service = new BroadcastService(new BroadcastTextSanitizer(), $resolver, $consent);

        $broadcast = $service->create(
            text: 'Dialogs and a group',
            scheduledAt: null,
            chatIds: [11, 22],
            segments: [$segment],
        );

        self::assertEqualsCanonicalizing([11, 22], $broadcast->recipients->pluck('chat_id')->all());
        self::assertEqualsCanonicalizing([11, 22], $broadcast->recipient_chat_ids);
    }
}
