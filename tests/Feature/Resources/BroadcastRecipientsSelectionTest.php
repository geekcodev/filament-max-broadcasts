<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcast;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

class BroadcastRecipientsSelectionTest extends TestCase
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

    public function testManualRecipientsUseOnlyActiveGroupsAndChannels(): void
    {
        $this->actingAs($this->adminUser());

        Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);
        Chats::create(22, 22, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);
        Chats::create(33, 33, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Channel]);
        Chats::create(44, 44, ['status' => MaxChatStatus::Stopped, 'chat_type' => ChatType::Chat]);

        Queue::fake();

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'type' => 'news',
                'text' => 'Groups and channels',
                'recipient_chat_ids' => [11, 22, 33, 44],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var Broadcast $broadcast */
        $broadcast = Broadcast::query()->firstOrFail();

        self::assertEqualsCanonicalizing([22, 33], $broadcast->recipients->pluck('chat_id')->all());
        self::assertEqualsCanonicalizing([22, 33], $broadcast->recipient_chat_ids);
    }

    public function testSegmentDialogsAndManualGroupAreCombined(): void
    {
        Queue::fake();
        $this->actingAs($this->adminUser());

        Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);
        Chats::create(22, 2, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);
        Chats::create(33, 33, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);

        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11, 22],
        ]);

        app(ConsentService::class)->optIn(11, 1, $segment->id);

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'type' => 'news',
                'text' => 'Segment dialogs and a group',
                'segment_ids' => [$segment->id],
                'recipient_chat_ids' => [33],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var Broadcast $broadcast */
        $broadcast = Broadcast::query()->firstOrFail();

        self::assertEqualsCanonicalizing([11, 33], $broadcast->recipients->pluck('chat_id')->all());
        self::assertEqualsCanonicalizing([11, 33], $broadcast->recipient_chat_ids);
    }

    public function testSegmentWithoutConsentedDialogsFailsValidation(): void
    {
        Queue::fake();
        $this->actingAs($this->adminUser());

        Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);
        Chats::create(22, 22, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);

        $segment = BroadcastSegment::query()->create([
            'name' => 'Без согласия',
            'chat_ids' => [11],
        ]);

        // Диалог выбыл из рассылок: сегмент пропадает из селекта, но значение уже выбрано в форме.
        MaxChat::query()->where('chat_id', 11)->update(['status' => MaxChatStatus::Stopped->value]);

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'type' => 'news',
                'text' => 'Hello',
                'segment_ids' => [$segment->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['segment_ids']);

        self::assertSame(0, Broadcast::query()->count());
        Queue::assertNothingPushed();
    }
}
