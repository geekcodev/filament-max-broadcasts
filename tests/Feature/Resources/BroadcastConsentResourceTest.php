<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastConsentResource;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\ChatType;

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

        $this->actingAs($this->adminUser());

        $this->get(BroadcastConsentResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('11')
            ->assertSee('22')
            ->assertSee(BroadcastConsentAction::OptIn->label())
            ->assertSee(BroadcastConsentAction::OptOut->label());
    }

    public function testIndexPageShowsChatNameAndType(): void
    {
        $segment = $this->createSegment();

        BroadcastConsent::query()->create([
            'segment_id' => $segment->id,
            'chat_id' => 11,
            'action' => BroadcastConsentAction::OptIn,
        ]);

        MaxChat::query()->create([
            'user_id' => 1,
            'chat_id' => 11,
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Dialog,
        ]);

        $this->actingAs($this->adminUser());

        $this->get(BroadcastConsentResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.chat_types.dialog'));
    }
}
