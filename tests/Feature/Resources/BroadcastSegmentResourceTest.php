<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\EditBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use Livewire\Livewire;

class BroadcastSegmentResourceTest extends TestCase
{
    private function adminUser(): TestUser
    {
        return TestUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'can_view_broadcasts' => true,
            'can_create_broadcasts' => true,
            'can_manage_broadcasts' => true,
        ]);
    }

    private function activeChats(): void
    {
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
        ]);
        Chats::create(22, 2, [
            'status' => MaxChatStatus::Active,
        ]);
        Chats::create(33, 3, [
            'status' => MaxChatStatus::Active,
        ]);
    }

    public function testIndexPageIsForbiddenWithoutPermission(): void
    {
        $this->actingAs(TestUser::query()->create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'password' => 'secret',
        ]));

        $this->get(BroadcastSegmentResource::getUrl('index'))->assertForbidden();
    }

    public function testIndexPageIsAccessibleWithPermission(): void
    {
        $this->actingAs($this->adminUser());

        $this->get(BroadcastSegmentResource::getUrl('index'))->assertSuccessful();
    }

    public function testCreateSegmentThroughForm(): void
    {
        $this->actingAs($this->adminUser());
        $this->activeChats();

        Livewire::test(CreateBroadcastSegment::class)
            ->fillForm([
                'name' => 'VIP clients',
                'chat_ids' => [11, 22],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var BroadcastSegment $segment */
        $segment = BroadcastSegment::query()->where('name', 'VIP clients')->firstOrFail();

        self::assertSame('VIP clients', $segment->name);
        self::assertSame([11, 22], $segment->chat_ids);
        self::assertSame(2, $segment->chat_count);
    }

    public function testCreateSegmentWithEmptyChatIds(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(CreateBroadcastSegment::class)
            ->fillForm([
                'name' => 'Newsletters',
                'chat_ids' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var BroadcastSegment $segment */
        $segment = BroadcastSegment::query()->where('name', 'Newsletters')->firstOrFail();

        self::assertSame([], $segment->chat_ids);
        self::assertSame(0, $segment->chat_count);
    }

    public function testCreateBroadcastWithSegmentAndManualRecipients(): void
    {
        $this->actingAs($this->adminUser());
        $this->activeChats();

        BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11, 22],
        ]);

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'type' => 'news',
                'text' => 'For VIPs',
                'recipient_chat_ids' => [11],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var Broadcast $broadcast */
        $broadcast = Broadcast::query()->firstOrFail();

        self::assertSame([11], $broadcast->recipient_chat_ids);
        self::assertSame([11], $broadcast->recipients->pluck('chat_id')->all());
        self::assertSame(1, $broadcast->total_recipients);
    }

    public function testCreateBroadcastWithMultipleSegments(): void
    {
        $this->actingAs($this->adminUser());
        $this->activeChats();

        $vip = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11, 22],
        ]);
        $employees = BroadcastSegment::query()->create([
            'name' => 'Employees',
            'chat_ids' => [33],
        ]);

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'type' => 'news',
                'text' => 'For everyone',
                'segment_ids' => [$vip->id, $employees->id],
                'recipient_chat_ids' => [11, 22, 33],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var Broadcast $broadcast */
        $broadcast = Broadcast::query()->latest('id')->firstOrFail();

        self::assertSame([11, 22, 33], $broadcast->recipients->pluck('chat_id')->all());
        self::assertSame(3, $broadcast->total_recipients);
        self::assertSame(
            [$vip->id, $employees->id],
            $broadcast->segments->pluck('id')->sort()->values()->all(),
        );
    }

    public function testEditPageIsForbiddenWithoutPermission(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        $this->actingAs(TestUser::query()->create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'password' => 'secret',
            'can_view_broadcasts' => true,
        ]));

        $this->get(BroadcastSegmentResource::getUrl('edit', ['record' => $segment->getKey()]))
            ->assertForbidden();
    }

    public function testEditPageIsAccessibleWithPermission(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        $this->actingAs($this->adminUser());

        $this->get(BroadcastSegmentResource::getUrl('edit', ['record' => $segment->getKey()]))
            ->assertSuccessful();
    }

    public function testEditSegmentThroughForm(): void
    {
        $this->actingAs($this->adminUser());
        $this->activeChats();

        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        Livewire::test(EditBroadcastSegment::class, ['record' => $segment->getKey()])
            ->fillForm([
                'name' => 'VIP clients',
                'description' => 'Постоянные клиенты',
                'chat_ids' => ['11', '22'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $segment->refresh();

        self::assertSame('VIP clients', $segment->name);
        self::assertSame('Постоянные клиенты', $segment->description);
        self::assertSame([11, 22], $segment->chat_ids);
        self::assertSame(2, $segment->chat_count);
    }

    public function testEditSegmentClearsDescriptionWhenOmitted(): void
    {
        $this->actingAs($this->adminUser());

        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'description' => 'Старое описание',
            'chat_ids' => [11],
        ]);

        Livewire::test(EditBroadcastSegment::class, ['record' => $segment->getKey()])
            ->fillForm([
                'name' => 'VIP',
                'description' => null,
                'chat_ids' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $segment->refresh();

        self::assertNull($segment->description);
        self::assertSame([], $segment->chat_ids);
        self::assertSame(0, $segment->chat_count);
    }

    public function testDeleteSegmentThroughHeaderAction(): void
    {
        $this->actingAs($this->adminUser());

        $segment = BroadcastSegment::query()->create([
            'name' => 'Удаляемый',
            'chat_ids' => [11],
        ]);

        Livewire::test(EditBroadcastSegment::class, ['record' => $segment->getKey()])
            ->callAction('delete');

        self::assertNull(BroadcastSegment::query()->find($segment->getKey()));
    }
}
