<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\EditBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ViewBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\RelationManagers\BroadcastConsentsRelationManager;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Models\MaxUser;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Livewire\Livewire;

class BroadcastSegmentConsentsRelationManagerTest extends TestCase
{
    private function user(bool $manage): TestUser
    {
        return TestUser::query()->create([
            'name' => $manage ? 'Admin' : 'Viewer',
            'email' => ($manage ? 'admin' : 'viewer').'@example.com',
            'password' => 'secret',
            'can_view_broadcasts' => true,
            'can_create_broadcasts' => $manage,
            'can_manage_broadcasts' => $manage,
        ]);
    }

    private function segment(string $name = 'VIP'): BroadcastSegment
    {
        return BroadcastSegment::query()->create([
            'name' => $name,
            'chat_ids' => [11],
        ]);
    }

    private function consent(BroadcastSegment $segment, int $chatId, BroadcastConsentAction $action = BroadcastConsentAction::OptIn): BroadcastConsent
    {
        return BroadcastConsent::query()->create([
            'segment_id' => $segment->getKey(),
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    /**
     * @return \Livewire\Features\SupportTesting\Testable<\Livewire\Component>
     */
    private function relationManager(BroadcastSegment $segment)
    {
        return Livewire::test(BroadcastConsentsRelationManager::class, [
            'ownerRecord' => $segment,
            'pageClass' => ViewBroadcastSegment::class,
        ]);
    }

    public function testRelationManagerIsRegisteredOnSegmentResource(): void
    {
        self::assertContains(BroadcastConsentsRelationManager::class, BroadcastSegmentResource::getRelations());
    }

    public function testTitleIsLocalized(): void
    {
        $title = BroadcastConsentsRelationManager::getTitle($this->segment(), ViewBroadcastSegment::class);

        self::assertSame(__('filament-max-broadcasts::broadcasts.consent.resource.plural_label'), $title);
    }

    public function testTableListsOnlyConsentsOfThisSegment(): void
    {
        $this->actingAs($this->user(manage: true));

        $segment = $this->segment('VIP');
        $other = $this->segment('Other');
        $own = $this->consent($segment, 11);
        $foreign = $this->consent($other, 22, BroadcastConsentAction::OptOut);

        $this->relationManager($segment)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertCanSeeTableRecords([$own])
            // @phpstan-ignore method.notFound
            ->assertCanNotSeeTableRecords([$foreign])
            ->assertTableFilterExists('action')
            ->assertTableColumnExists('name')
            ->assertTableColumnExists('action')
            ->assertTableColumnExists('source')
            ->assertTableColumnExists('created_at');
    }

    public function testSegmentColumnIsOmittedBecauseOwnerIsKnown(): void
    {
        $this->actingAs($this->user(manage: true));

        $segment = $this->segment();
        $this->consent($segment, 11);

        $this->relationManager($segment)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertTableColumnDoesNotExist('segment.name');
    }

    public function testChatAndUserIdsRenderAsBadges(): void
    {
        $this->actingAs($this->user(manage: true));

        Chats::create(11, 1, ['chat_type' => ChatType::Dialog]);
        $segment = $this->segment();
        $consent = BroadcastConsent::query()->create([
            'segment_id' => $segment->getKey(),
            'chat_id' => 11,
            'user_id' => 5,
            'action' => BroadcastConsentAction::OptIn,
        ]);

        $this->relationManager($segment)
            // @phpstan-ignore method.notFound
            ->assertCanSeeTableRecords([$consent])
            ->assertSee('fi-badge', false)
            ->assertSee(__('filament-max-broadcasts::broadcasts.chat_types.dialog'))
            ->assertSee('ID: 11')
            ->assertSee('ID: 5');
    }

    public function testNameColumnFallsBackToAnonymousLabelWithoutChat(): void
    {
        $this->actingAs($this->user(manage: true));

        $segment = $this->segment();
        $consent = $this->consent($segment, 99);

        $this->relationManager($segment)
            ->assertTableColumnFormattedStateSet(
                'name',
                __('filament-max-broadcasts::broadcasts.consent_table.anonymous_chat', ['id' => 99]),
                $consent,
            );
    }

    public function testSearchByChatUserNameFindsConsent(): void
    {
        $this->actingAs($this->user(manage: true));

        Chats::create(11, 1);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
        ]);
        Chats::create(22, 2);
        MaxUser::query()->create([
            'user_id' => 2,
            'first_name' => 'Сидор',
            'last_name' => 'Сидоров',
        ]);

        $segment = $this->segment();
        $found = $this->consent($segment, 11);
        $other = $this->consent($segment, 22);

        $this->relationManager($segment)
            ->searchTable('Петров')
            // @phpstan-ignore method.notFound
            ->assertCanSeeTableRecords([$found])
            // @phpstan-ignore method.notFound
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function testDeleteActionIsVisibleWithManagePermission(): void
    {
        $this->actingAs($this->user(manage: true));

        $segment = $this->segment();
        $consent = $this->consent($segment, 11);

        $this->relationManager($segment)
            // @phpstan-ignore method.notFound
            ->assertTableActionVisible('delete', $consent);
    }

    public function testDeleteActionIsHiddenWithoutManagePermission(): void
    {
        $this->actingAs($this->user(manage: false));

        $segment = $this->segment();
        $consent = $this->consent($segment, 11);

        $this->relationManager($segment)
            // @phpstan-ignore method.notFound
            ->assertTableActionHidden('delete', $consent);
    }

    public function testViewPageShowsConsentsRelationManager(): void
    {
        $this->actingAs($this->user(manage: false));

        $segment = $this->segment();

        Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.consent.resource.plural_label'));
    }

    public function testEditPageShowsConsentsRelationManager(): void
    {
        $this->actingAs($this->user(manage: true));

        $segment = $this->segment();

        Livewire::test(EditBroadcastSegment::class, ['record' => $segment->getKey()])
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.consent.resource.plural_label'));
    }
}
