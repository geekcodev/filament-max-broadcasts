<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use Filament\Actions\Action;
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
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
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

        Livewire::test(CreateBroadcastSegment::class)
            ->fillForm([
                'name' => 'VIP clients',
                'description' => 'Постоянные клиенты',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(BroadcastSegmentResource::getUrl('index'));

        /** @var BroadcastSegment $segment */
        $segment = BroadcastSegment::query()->where('name', 'VIP clients')->firstOrFail();

        self::assertSame('VIP clients', $segment->name);
        self::assertSame('Постоянные клиенты', $segment->description);
        self::assertSame([], $segment->chat_ids);
        self::assertSame(0, $segment->chat_count);
    }

    public function testCreateSegmentWithEmptyChatIds(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(CreateBroadcastSegment::class)
            ->fillForm([
                'name' => 'Newsletters',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var BroadcastSegment $segment */
        $segment = BroadcastSegment::query()->where('name', 'Newsletters')->firstOrFail();

        self::assertSame([], $segment->chat_ids);
        self::assertSame(0, $segment->chat_count);
    }

    public function testCreateBroadcastWithManualGroupRecipient(): void
    {
        $this->actingAs($this->adminUser());
        $this->activeChats();

        Chats::create(44, 44, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Chat,
        ]);

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'type' => 'news',
                'text' => 'For a group',
                'recipient_chat_ids' => [44],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var Broadcast $broadcast */
        $broadcast = Broadcast::query()->firstOrFail();

        self::assertSame([44], $broadcast->recipient_chat_ids);
        self::assertSame([44], $broadcast->recipients->pluck('chat_id')->all());
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

        $consent = app(ConsentService::class);
        $consent->optIn(11, 1, $vip->id);
        $consent->optIn(22, 2, $vip->id);
        $consent->optIn(33, 3, $employees->id);

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
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(BroadcastSegmentResource::getUrl('index'));

        $segment->refresh();

        self::assertSame('VIP clients', $segment->name);
        self::assertSame('Постоянные клиенты', $segment->description);
        self::assertSame([11], $segment->chat_ids);
        self::assertSame(1, $segment->chat_count);
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
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $segment->refresh();

        self::assertNull($segment->description);
        self::assertSame([11], $segment->chat_ids);
        self::assertSame(1, $segment->chat_count);
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

    public function testViewPageShowsSegmentDetailsAndRecipientsSummary(): void
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
            ->assertSee(__('filament-max-broadcasts::broadcasts.segment.view.recipients_count', ['count' => 2]))
            ->assertSee(__('filament-max-broadcasts::broadcasts.segment.view.manage_recipients'))
            ->assertSee(__('filament-max-broadcasts::broadcasts.segment.tabs.recipients'))
            ->assertSee(__('filament-max-broadcasts::broadcasts.segment.tabs.consents'));
    }

    public function testViewPageShowsRecipientsSummaryInsteadOfFullList(): void
    {
        $this->actingAs($this->adminUser());

        Chats::create(11, 1, ['chat_type' => ChatType::Dialog]);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
        ]);

        $segment = BroadcastSegment::query()->create([
            'name' => 'Summary',
            'chat_ids' => [11],
        ]);

        Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.segment.view.recipients_count', ['count' => 1]))
            ->assertDontSee('Иван Петров');
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
            ->assertActionVisible('create')
            // @phpstan-ignore method.notFound
            ->assertActionExists('create', static fn (Action $action): bool => $action->isOutlined());
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

    public function testCreatePageUsesOutlinedActionsWithIconsAndWithoutCreateAnother(): void
    {
        $this->actingAs($this->adminUser());

        /** @var CreateBroadcastSegment $page */
        $page = Livewire::test(CreateBroadcastSegment::class)->instance();

        $actions = $this->formActions($page);

        self::assertArrayNotHasKey('createAnother', $actions);
        self::assertArrayHasKey('create', $actions);

        self::assertTrue($actions['create']->isOutlined());
        self::assertSame('heroicon-o-plus', $actions['create']->getIcon());

        self::assertTrue($actions['cancel']->isOutlined());
        self::assertSame('heroicon-o-x-mark', $actions['cancel']->getIcon());
    }

    public function testEditPageUsesOutlinedActionsWithIcons(): void
    {
        $this->actingAs($this->adminUser());

        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);

        /** @var EditBroadcastSegment $page */
        $page = Livewire::test(EditBroadcastSegment::class, ['record' => $segment->getKey()])->instance();

        $actions = $this->formActions($page);

        self::assertArrayHasKey('save', $actions);
        self::assertTrue($actions['save']->isOutlined());
        self::assertSame('heroicon-o-check', $actions['save']->getIcon());

        self::assertTrue($actions['cancel']->isOutlined());
        self::assertSame('heroicon-o-x-mark', $actions['cancel']->getIcon());
    }

    /**
     * @return array<string, Action>
     */
    private function formActions(object $page): array
    {
        $method = new \ReflectionMethod($page, 'getFormActions');
        $result = $method->invoke($page);
        $actions = [];

        foreach (is_array($result) ? $result : [] as $component) {
            if (! $component instanceof Action) {
                continue;
            }

            $name = $component->getName();

            if ($name !== null) {
                $actions[$name] = $component;
            }
        }

        return $actions;
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
