<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
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
        MaxChat::query()->create(['user_id' => 1, 'chat_id' => 11, 'status' => MaxChatStatus::Active]);
        MaxChat::query()->create(['user_id' => 2, 'chat_id' => 22, 'status' => MaxChatStatus::Active]);
        MaxChat::query()->create(['user_id' => 3, 'chat_id' => 33, 'status' => MaxChatStatus::Active]);
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
}
