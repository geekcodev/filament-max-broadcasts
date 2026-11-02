<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\EditBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcastSegments;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ViewBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxUser;
use GeekCo\MaxPhpClient\Enum\ChatType;
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
            'chat_type' => ChatType::Dialog,
        ]);
        Chats::create(22, 2, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Dialog,
        ]);
        Chats::create(33, 3, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Dialog,
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

    public function testDeletingSegmentKeepsConsentHistory(): void
    {
        $this->actingAs($this->adminUser());

        $segment = BroadcastSegment::query()->create([
            'name' => 'Удаляемый с согласиями',
            'chat_ids' => [11],
        ]);

        BroadcastConsent::query()->create([
            'segment_id' => $segment->id,
            'chat_id' => 11,
            'action' => BroadcastConsentAction::OptIn,
        ]);

        Livewire::test(EditBroadcastSegment::class, ['record' => $segment->getKey()])
            ->callAction('delete');

        self::assertNull(BroadcastSegment::query()->find($segment->getKey()));

        /** @var BroadcastConsent $consent */
        $consent = BroadcastConsent::query()->firstOrFail();

        self::assertNull($consent->segment_id);
        self::assertSame(11, $consent->chat_id);
    }

    public function testViewPageIsForbiddenWithoutPermission(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        $this->actingAs(TestUser::query()->create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'password' => 'secret',
        ]));

        $this->get(BroadcastSegmentResource::getUrl('view', ['record' => $segment->getKey()]))
            ->assertForbidden();
    }

    public function testViewPageIsAccessibleWithViewPermissionOnly(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        $this->actingAs(TestUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'secret',
            'can_view_broadcasts' => true,
        ]));

        $this->get(BroadcastSegmentResource::getUrl('view', ['record' => $segment->getKey()]))
            ->assertSuccessful();

        $component = Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->assertSuccessful();

        // @phpstan-ignore method.notFound
        $component->assertActionHidden('edit');
        // @phpstan-ignore method.notFound
        $component->assertActionHidden('delete');
    }

    public function testViewPageShowsSegmentDetailsAndRecipientNames(): void
    {
        $this->actingAs($this->adminUser());

        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
        ]);
        MaxUser::query()->create([
            'user_id' => 2,
            'first_name' => 'Мария',
            'last_name' => 'Сидорова',
        ]);

        Chats::create(11, 1, ['chat_type' => ChatType::Dialog]);
        Chats::create(22, 2, ['chat_type' => ChatType::Dialog]);

        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP clients',
            'description' => 'Постоянные клиенты',
            'chat_ids' => [11, 22],
        ]);

        Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->assertSuccessful()
            ->assertSee('VIP clients')
            ->assertSee('Постоянные клиенты')
            ->assertSee('Иван Петров')
            ->assertSee('Мария Сидорова');
    }

    public function testViewPageRendersRecipientsAsBadgesLikeSelect(): void
    {
        $this->actingAs($this->adminUser());

        Chats::create(11, 1, ['chat_type' => ChatType::Dialog]);

        $segment = BroadcastSegment::query()->create([
            'name' => 'Badges',
            'chat_ids' => [11],
        ]);

        Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->assertSuccessful()
            ->assertSee('fi-badge', false)
            ->assertSee(__('filament-max-broadcasts::broadcasts.chat_types.dialog'))
            ->assertSee('(ID: 11)');
    }

    public function testViewPageFallsBackToChatIdForUnknownRecipient(): void
    {
        $this->actingAs($this->adminUser());

        $segment = BroadcastSegment::query()->create([
            'name' => 'Unknown',
            'chat_ids' => [99],
        ]);

        Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.segment.view.chat_fallback', ['id' => 99]));
    }

    public function testViewPageShowsEmptyRecipientsPlaceholder(): void
    {
        $this->actingAs($this->adminUser());

        $segment = BroadcastSegment::query()->create([
            'name' => 'Empty',
            'chat_ids' => [],
        ]);

        Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.segment.view.no_recipients'));
    }

    public function testViewPageShowsManageActionsForAdmin(): void
    {
        $this->actingAs($this->adminUser());

        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        $component = Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->assertSuccessful();

        // @phpstan-ignore method.notFound
        $component->assertActionVisible('edit');
        // @phpstan-ignore method.notFound
        $component->assertActionVisible('delete');
    }

    public function testDeleteSegmentThroughViewPageAction(): void
    {
        $this->actingAs($this->adminUser());

        $segment = BroadcastSegment::query()->create([
            'name' => 'Удаляемый',
            'chat_ids' => [11],
        ]);

        Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->callAction('delete');

        self::assertNull(BroadcastSegment::query()->find($segment->getKey()));
    }

    public function testListTableShowsViewAction(): void
    {
        $this->actingAs($this->adminUser());

        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        Livewire::test(ListBroadcastSegments::class)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertTableActionVisible('view', $segment);
    }

    public function testCreateActionIsVisibleWithCreatePermission(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcastSegments::class)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertActionVisible('create');
    }

    public function testCreateActionIsHiddenWithoutCreatePermission(): void
    {
        $this->actingAs(TestUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'secret',
            'can_view_broadcasts' => true,
        ]));

        Livewire::test(ListBroadcastSegments::class)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertActionHidden('create');
    }

    public function testCreatePageIsForbiddenWithoutCreatePermission(): void
    {
        $this->actingAs(TestUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'secret',
            'can_view_broadcasts' => true,
        ]));

        $this->get(BroadcastSegmentResource::getUrl('create'))->assertForbidden();
    }

    public function testCreateSegmentRejectsGroupChat(): void
    {
        $this->actingAs($this->adminUser());

        Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);
        Chats::create(33, 33, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);

        $before = BroadcastSegment::query()->count();

        Livewire::test(CreateBroadcastSegment::class)
            ->fillForm([
                'name' => 'Mixed',
                'chat_ids' => [11, 33],
            ])
            ->call('create')
            // @phpstan-ignore method.notFound
            ->assertHasFormErrors(['chat_ids']);

        self::assertSame($before, BroadcastSegment::query()->count());
    }

    public function testSelectAllInSegmentFormSelectsOnlyDialogs(): void
    {
        $this->actingAs($this->adminUser());

        Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);
        Chats::create(22, 2, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);
        Chats::create(33, 33, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);

        Livewire::test(CreateBroadcastSegment::class)
            ->callFormComponentAction('chat_ids', 'select_all')
            // @phpstan-ignore method.notFound
            ->assertFormSet(['chat_ids' => [11, 22]]);
    }

    public function testViewPageWarnsAboutNonDialogRecipients(): void
    {
        $this->actingAs($this->adminUser());

        Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);
        Chats::create(33, 33, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);

        $segment = BroadcastSegment::query()->create([
            'name' => 'Legacy',
            'chat_ids' => [11, 33],
        ]);

        Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.segment.view.non_dialog_warning', ['count' => 1]));
    }
}
