<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastSegmentResource;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcastSegmentConsents;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Models\MaxUser;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Livewire\Livewire;

class ListBroadcastSegmentConsentsTest extends TestCase
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

    private function consent(
        BroadcastSegment $segment,
        int $chatId,
        BroadcastConsentAction $action = BroadcastConsentAction::OptIn,
    ): BroadcastConsent {
        return BroadcastConsent::query()->create([
            'segment_id' => $segment->getKey(),
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    /**
     * @return \Livewire\Features\SupportTesting\Testable<\Livewire\Component>
     */
    private function page(BroadcastSegment $segment)
    {
        return Livewire::test(ListBroadcastSegmentConsents::class, [
            'record' => $segment->getKey(),
        ]);
    }

    public function testPageIsForbiddenWithoutViewPermission(): void
    {
        $segment = $this->segment();

        $this->get(BroadcastSegmentResource::getUrl('consents', ['record' => $segment]))
            ->assertForbidden();
    }

    public function testPageIsAccessibleWithViewPermission(): void
    {
        $this->actingAs($this->user(manage: false));

        $segment = $this->segment();

        $this->get(BroadcastSegmentResource::getUrl('consents', ['record' => $segment]))
            ->assertSuccessful();
    }

    public function testTableListsOnlyConsentsOfThisSegment(): void
    {
        $this->actingAs($this->user(manage: true));

        $segment = $this->segment('VIP');
        $other = $this->segment('Other');
        $own = $this->consent($segment, 11);
        $foreign = $this->consent($other, 22, BroadcastConsentAction::OptOut);

        $this->page($segment)
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

    public function testTableShowsPendingRequestsWithTheirLabel(): void
    {
        $this->actingAs($this->user(manage: true));

        $segment = $this->segment();
        $pending = $this->consent($segment, 11, BroadcastConsentAction::Pending);

        $this->page($segment)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertCanSeeTableRecords([$pending])
            ->assertSee(BroadcastConsentAction::Pending->label());
    }

    public function testEmptyConsentStateIsLocalized(): void
    {
        $this->actingAs($this->user(manage: true));

        $this->page($this->segment())
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.consent_table.empty_heading'))
            ->assertDontSee('broadcast consents');
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

        $this->page($segment)
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

        $this->page($segment)
            // @phpstan-ignore method.notFound
            ->assertTableActionVisible('delete', $consent);
    }

    public function testDeleteActionIsHiddenWithoutManagePermission(): void
    {
        $this->actingAs($this->user(manage: false));

        $segment = $this->segment();
        $consent = $this->consent($segment, 11);

        $this->page($segment)
            // @phpstan-ignore method.notFound
            ->assertTableActionHidden('delete', $consent);
    }

    public function testChatNameIsRenderedWithoutTypeBadges(): void
    {
        $this->actingAs($this->user(manage: true));

        Chats::create(11, 1, ['chat_type' => ChatType::Dialog]);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
        ]);

        $segment = $this->segment();
        $consent = $this->consent($segment, 11);

        $this->page($segment)
            // @phpstan-ignore method.notFound
            ->assertCanSeeTableRecords([$consent])
            ->assertSee('Иван')
            ->assertDontSee('ID: 11');
    }
}
