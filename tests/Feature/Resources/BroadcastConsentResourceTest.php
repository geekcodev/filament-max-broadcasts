<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastConsentResource;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcastConsents;
use GeekCo\FilamentMaxBroadcasts\Resources\Tables\BroadcastConsentsTable;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxUser;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Filament\Tables\Filters\SelectFilter;
use Livewire\Livewire;

class BroadcastConsentResourceTest extends TestCase
{
    private function adminUser(): TestUser
    {
        return TestUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'can_view_broadcasts' => true,
            'can_manage_broadcasts' => true,
        ]);
    }

    private function guestUser(): TestUser
    {
        return TestUser::query()->create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'password' => 'secret',
        ]);
    }

    private function createSegment(): BroadcastSegment
    {
        return BroadcastSegment::query()->create([
            'name' => 'Новости и акции',
            'chat_ids' => [11, 22],
        ]);
    }

    public function testIndexPageIsForbiddenWithoutPermission(): void
    {
        $this->actingAs($this->guestUser());

        $this->get(BroadcastConsentResource::getUrl('index'))->assertForbidden();
    }

    public function testIndexPageIsAccessibleWithPermission(): void
    {
        $this->actingAs($this->adminUser());

        $this->get(BroadcastConsentResource::getUrl('index'))->assertSuccessful();
    }

    public function testIndexPageListsConsents(): void
    {
        $segment = $this->createSegment();

        BroadcastConsent::query()->create([
            'segment_id' => $segment->id,
            'chat_id' => 11,
            'action' => BroadcastConsentAction::OptIn,
        ]);
        BroadcastConsent::query()->create([
            'segment_id' => $segment->id,
            'chat_id' => 22,
            'action' => BroadcastConsentAction::OptOut,
        ]);
        BroadcastConsent::query()->create([
            'segment_id' => $segment->id,
            'chat_id' => 33,
            'action' => BroadcastConsentAction::Pending,
            'source' => 'request',
        ]);

        $this->actingAs($this->adminUser());

        $this->get(BroadcastConsentResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('11')
            ->assertSee('22')
            ->assertSee('33')
            ->assertSee(BroadcastConsentAction::Pending->label())
            ->assertSee(BroadcastConsentAction::OptIn->label())
            ->assertSee(BroadcastConsentAction::OptOut->label())
            ->assertSee(__('filament-max-broadcasts::broadcasts.consent_table.source_values.request'));
    }

    public function testIndexPageShowsChatNameAndId(): void
    {
        $segment = $this->createSegment();

        BroadcastConsent::query()->create([
            'segment_id' => $segment->id,
            'chat_id' => 11,
            'action' => BroadcastConsentAction::OptIn,
        ]);

        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Dialog,
        ]);
        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
        ]);

        $this->actingAs($this->adminUser());

        $this->get(BroadcastConsentResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Иван')
            ->assertSee('11')
            ->assertDontSee(__('filament-max-broadcasts::broadcasts.chat_types.dialog'));
    }
    public function testIndexPageShowsLocalizedEmptyState(): void
    {
        $this->actingAs($this->adminUser());

        $this->get(BroadcastConsentResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.consent_table.empty_heading'))
            ->assertDontSee('broadcast consents');
    }

    public function testIndexPageFiltersConsentsBySegment(): void
    {
        $first = $this->createSegment();
        $second = BroadcastSegment::query()->create([
            'name' => 'Другой сегмент',
            'chat_ids' => [33],
        ]);

        $inFirst = $this->consent($first->id, 11);
        $inSecond = $this->consent($second->id, 22);

        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcastConsents::class)
            ->filterTable('segment_id', $first->id)
            ->assertCanSeeTableRecords([$inFirst])
            ->assertCanNotSeeTableRecords([$inSecond]);
    }

    public function testIndexPageFiltersConsentsOfDeletedSegments(): void
    {
        $segment = $this->createSegment();
        $other = BroadcastSegment::query()->create([
            'name' => 'Другой сегмент',
            'chat_ids' => [33],
        ]);

        $inSegment = $this->consent($segment->id, 11);
        $inOther = $this->consent($other->id, 22);

        $segment->delete();

        /** @var BroadcastConsent $orphan */
        $orphan = BroadcastConsent::query()->where('chat_id', 11)->firstOrFail();

        self::assertNull($orphan->segment_id);

        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcastConsents::class)
            ->filterTable('segment_id', 'none')
            ->assertCanSeeTableRecords([$orphan])
            ->assertCanNotSeeTableRecords([$inOther]);
    }

    public function testIndexPageShowsUnknownSourceAsStoredValue(): void
    {
        $segment = $this->createSegment();

        BroadcastConsent::query()->create([
            'segment_id' => $segment->id,
            'chat_id' => 11,
            'action' => BroadcastConsentAction::OptIn,
            'source' => 'legacy',
        ]);

        $this->actingAs($this->adminUser());

        $this->get(BroadcastConsentResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('legacy');
    }

    public function testIndexPageFiltersPendingConsents(): void
    {
        $segment = $this->createSegment();

        $pending = $this->consent($segment->id, 11, BroadcastConsentAction::Pending);
        $answered = $this->consent($segment->id, 22);

        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcastConsents::class)
            ->filterTable('action', BroadcastConsentAction::Pending->value)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$answered]);
    }

    public function testIndexPageShowsUserIdWhenPresent(): void
    {
        $segment = $this->createSegment();
        $consent = BroadcastConsent::query()->create([
            'segment_id' => $segment->id,
            'chat_id' => 11,
            'user_id' => 1,
            'action' => BroadcastConsentAction::OptIn,
        ]);

        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcastConsents::class)
            // @phpstan-ignore method.notFound
            ->assertTableColumnStateSet('user_id', '1', $consent);
    }

    public function testSegmentFilterIgnoresMalformedValue(): void
    {
        $segment = $this->createSegment();
        $first = $this->consent($segment->id, 11);
        $second = $this->consent($segment->id, 22);

        $filter = (new \ReflectionMethod(BroadcastConsentsTable::class, 'segmentFilter'))->invoke(null);
        self::assertInstanceOf(SelectFilter::class, $filter);

        $query = BroadcastConsent::query();
        $filter->apply($query, ['value' => []]);

        self::assertSame([$first->id, $second->id], $query->pluck('id')->all());
    }

    private function consent(
        ?int $segmentId,
        int $chatId,
        BroadcastConsentAction $action = BroadcastConsentAction::OptIn,
    ): BroadcastConsent {
        return BroadcastConsent::query()->create([
            'segment_id' => $segmentId,
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }
}
