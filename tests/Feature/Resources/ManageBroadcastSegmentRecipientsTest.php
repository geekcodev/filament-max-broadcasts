<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ManageBroadcastSegmentRecipients;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\LaravelMaxClient\Models\MaxUser;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Livewire\Livewire;

class ManageBroadcastSegmentRecipientsTest extends TestCase
{
    private function user(bool $manage = true): TestUser
    {
        return TestUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'can_view_broadcasts' => true,
            'can_manage_broadcasts' => $manage,
        ]);
    }

    /**
     * @param  list<int>  $chatIds
     */
    private function segment(string $name = 'VIP', array $chatIds = []): BroadcastSegment
    {
        return BroadcastSegment::query()->create([
            'name' => $name,
            'chat_ids' => $chatIds,
        ]);
    }

    private function dialog(int $chatId, int $userId, ?string $lastName = null): MaxChat
    {
        $chat = Chats::create($chatId, $userId, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Dialog,
        ]);

        MaxUser::query()->create([
            'user_id' => $userId,
            'first_name' => 'Имя'.$userId,
            'last_name' => $lastName,
        ]);

        return $chat;
    }

    private function consent(BroadcastSegment $segment, int $chatId, BroadcastConsentAction $action): BroadcastConsent
    {
        return BroadcastConsent::query()->create([
            'segment_id' => $segment->getKey(),
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    /**
     * @param  array<Action|ActionGroup>  $actions
     * @return array<string, Action>
     */
    private function actionsByName(array $actions): array
    {
        $named = [];

        foreach ($actions as $action) {
            if ($action instanceof Action && $action->getName() !== null) {
                $named[$action->getName()] = $action;
            }
        }

        return $named;
    }

    public function testPageIsForbiddenWithoutViewPermission(): void
    {
        $segment = $this->segment();

        $this->get(BroadcastSegmentResource::getUrl('recipients', ['record' => $segment]))
            ->assertForbidden();
    }

    public function testPageIsAccessibleWithViewPermission(): void
    {
        $this->actingAs($this->user(manage: false));

        $segment = $this->segment();

        $this->get(BroadcastSegmentResource::getUrl('recipients', ['record' => $segment]))
            ->assertSuccessful();
    }

    public function testTableListsOnlyActiveDialogs(): void
    {
        $this->actingAs($this->user());

        $dialog = $this->dialog(11, 1);
        $group = Chats::create(22, 2, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);
        $inactive = Chats::create(33, 3, ['status' => MaxChatStatus::Stopped, 'chat_type' => ChatType::Dialog]);

        $segment = $this->segment('VIP', [11]);

        Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()])
            // @phpstan-ignore method.notFound
            ->assertCanSeeTableRecords([$dialog])
            // @phpstan-ignore method.notFound
            ->assertCanNotSeeTableRecords([$group, $inactive]);
    }

    public function testSelectedRecordsAreLoadedFromSegment(): void
    {
        $this->actingAs($this->user());

        $this->dialog(11, 1);
        $this->dialog(22, 2);
        $segment = $this->segment('VIP', [11]);

        /** @var \Livewire\Features\SupportTesting\Testable<\Livewire\Component> $component */
        $component = Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()]);

        self::assertEqualsCanonicalizing([11], $component->get('selectedTableRecords'));
    }

    public function testSelectionActionsLiveInToolbarAndSaveInHeader(): void
    {
        $this->actingAs($this->user());

        $segment = $this->segment();

        /** @var \Livewire\Features\SupportTesting\Testable<\Livewire\Component> $component */
        $component = Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()]);

        /** @var ManageBroadcastSegmentRecipients $page */
        $page = $component->instance();

        $headerActions = $this->actionsByName($page->getTable()->getHeaderActions());
        $toolbarActions = $this->actionsByName($page->getTable()->getToolbarActions());

        self::assertSame(['saveSelection'], array_keys($headerActions));
        self::assertSame(['selectAll', 'clearSelection'], array_keys($toolbarActions));

        self::assertSame('selectAllRecords()', $toolbarActions['selectAll']->getAlpineClickHandler());
        self::assertSame('deselectAllRecords()', $toolbarActions['clearSelection']->getAlpineClickHandler());

        foreach ([...$headerActions, ...$toolbarActions] as $action) {
            self::assertTrue($action->isOutlined());
        }
    }

    public function testSaveSelectionPersistsSelectedDialogs(): void
    {
        $this->actingAs($this->user());

        $first = $this->dialog(11, 1);
        $second = $this->dialog(22, 2);

        $segment = $this->segment('VIP', [11]);

        Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()])
            ->selectTableRecords([$first, $second])
            ->call('saveSelection');

        self::assertSame([11, 22], $segment->refresh()->chat_ids);
    }

    public function testSaveSelectionPreservesNonDialogAndInactiveIds(): void
    {
        $this->actingAs($this->user());

        $first = $this->dialog(11, 1);
        $second = $this->dialog(22, 2);
        Chats::create(33, 33, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);
        Chats::create(44, 44, ['status' => MaxChatStatus::Stopped, 'chat_type' => ChatType::Dialog]);

        $segment = $this->segment('VIP', [33, 44]);

        Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()])
            ->selectTableRecords([$first, $second])
            ->call('saveSelection');

        self::assertSame([11, 22, 33, 44], $segment->refresh()->chat_ids);
    }

    public function testSaveInTrackingModeUsesDeselectedRecords(): void
    {
        $this->actingAs($this->user());

        $this->dialog(11, 1);
        $this->dialog(22, 2);

        $segment = $this->segment();

        /** @var \Livewire\Features\SupportTesting\Testable<\Livewire\Component> $component */
        $component = Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()]);
        $component->set('isTrackingDeselectedTableRecords', true);
        $component->set('deselectedTableRecords', [22]);
        $component->call('saveSelection');

        self::assertSame([11], $segment->refresh()->chat_ids);
    }

    public function testSaveEmptySelectionRemovesActiveDialogs(): void
    {
        $this->actingAs($this->user());

        $this->dialog(11, 1);
        $this->dialog(22, 2);
        Chats::create(33, 33, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);

        $segment = $this->segment('VIP', [11, 33]);

        /** @var \Livewire\Features\SupportTesting\Testable<\Livewire\Component> $component */
        $component = Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()]);
        $component->set('selectedTableRecords', []);
        $component->call('saveSelection');

        self::assertSame([33], $segment->refresh()->chat_ids);
    }

    public function testConsentStatusColumnReflectsSegmentConsents(): void
    {
        $this->actingAs($this->user());

        $pendingChat = $this->dialog(11, 1);
        $optInChat = $this->dialog(22, 2);
        $optOutChat = $this->dialog(33, 3);
        $emptyChat = $this->dialog(44, 4);
        $foreignChat = $this->dialog(55, 5);

        $segment = $this->segment();
        $other = $this->segment('Other');

        $this->consent($segment, 11, BroadcastConsentAction::Pending);
        $this->consent($segment, 22, BroadcastConsentAction::OptIn);
        $this->consent($segment, 33, BroadcastConsentAction::OptOut);
        $this->consent($other, 55, BroadcastConsentAction::OptIn);

        Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()])
            ->assertTableColumnExists('consent_status')
            // @phpstan-ignore method.notFound
            ->assertTableColumnStateSet('consent_status', BroadcastConsentAction::Pending, $pendingChat)
            // @phpstan-ignore method.notFound
            ->assertTableColumnStateSet('consent_status', BroadcastConsentAction::OptIn, $optInChat)
            // @phpstan-ignore method.notFound
            ->assertTableColumnStateSet('consent_status', BroadcastConsentAction::OptOut, $optOutChat)
            // @phpstan-ignore method.notFound
            ->assertTableColumnStateSet('consent_status', null, $emptyChat)
            // @phpstan-ignore method.notFound
            ->assertTableColumnStateSet('consent_status', null, $foreignChat);
    }

    public function testSearchFindsDialogByUserName(): void
    {
        $this->actingAs($this->user());

        $found = $this->dialog(11, 1, 'Петров');
        $other = $this->dialog(22, 2, 'Сидоров');

        $segment = $this->segment();

        Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()])
            ->searchTable('Петров')
            // @phpstan-ignore method.notFound
            ->assertCanSeeTableRecords([$found])
            // @phpstan-ignore method.notFound
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function testOnlySelectedFilterUsesCurrentSelection(): void
    {
        $this->actingAs($this->user());

        $selected = $this->dialog(11, 1);
        $other = $this->dialog(22, 2);

        $segment = $this->segment('VIP', [11]);

        Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()])
            ->filterTable('selected')
            // @phpstan-ignore method.notFound
            ->assertCanSeeTableRecords([$selected])
            // @phpstan-ignore method.notFound
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function testOnlySelectedFilterRespectsDeselectedRecordsInTrackingMode(): void
    {
        $this->actingAs($this->user());

        $selected = $this->dialog(11, 1);
        $other = $this->dialog(22, 2);

        $segment = $this->segment();

        /** @var \Livewire\Features\SupportTesting\Testable<\Livewire\Component> $component */
        $component = Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()]);
        $component->set('isTrackingDeselectedTableRecords', true);
        $component->set('deselectedTableRecords', [22]);
        $component->filterTable('selected')
            // @phpstan-ignore method.notFound
            ->assertCanSeeTableRecords([$selected])
            // @phpstan-ignore method.notFound
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function testSaveActionIsHiddenWithoutManagePermission(): void
    {
        $this->actingAs($this->user(manage: false));

        $this->dialog(11, 1);
        $segment = $this->segment('VIP', [11]);

        /** @var \Livewire\Features\SupportTesting\Testable<\Livewire\Component> $component */
        $component = Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()]);

        /** @var ManageBroadcastSegmentRecipients $page */
        $page = $component->instance();

        $actions = [
            ...$this->actionsByName($page->getTable()->getHeaderActions()),
            ...$this->actionsByName($page->getTable()->getToolbarActions()),
        ];

        self::assertFalse($actions['selectAll']->isVisible());
        self::assertFalse($actions['clearSelection']->isVisible());
        self::assertFalse($actions['saveSelection']->isVisible());
    }

    public function testSubheadingShowsTotalAndSelectedCount(): void
    {
        $this->actingAs($this->user());

        $this->dialog(11, 1);
        $this->dialog(22, 2);
        $segment = $this->segment('VIP', [11]);

        Livewire::test(ManageBroadcastSegmentRecipients::class, ['record' => $segment->getKey()])
            ->assertSee(__('filament-max-broadcasts::broadcasts.segment.recipients_page.summary', [
                'total' => 2,
            ]));
    }
}
