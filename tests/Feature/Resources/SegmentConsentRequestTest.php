<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Jobs\SendConsentRequestsJob;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcasts;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcastSegments;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ViewBroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

class SegmentConsentRequestTest extends TestCase
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

    private function viewerUser(): TestUser
    {
        return TestUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'secret',
            'can_view_broadcasts' => true,
        ]);
    }

    private function segment(string $name = 'VIP'): BroadcastSegment
    {
        return BroadcastSegment::query()->create([
            'name' => $name,
            'chat_ids' => [],
        ]);
    }

    private function activeDialog(int $chatId, int $userId): void
    {
        Chats::create($chatId, $userId, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Dialog,
        ]);
    }

    public function testRequestConsentIsNotOnBroadcastsList(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcasts::class)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertActionDoesNotExist('request_consent');
    }

    public function testRequestConsentIsVisibleOnSegmentViewPageForCreator(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(ViewBroadcastSegment::class, ['record' => $this->segment()->getKey()])
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertActionVisible('request_consent');
    }

    public function testRequestConsentIsHiddenOnSegmentViewPageWithoutCreatePermission(): void
    {
        $this->actingAs($this->viewerUser());

        Livewire::test(ViewBroadcastSegment::class, ['record' => $this->segment()->getKey()])
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertActionHidden('request_consent');
    }

    public function testRequestConsentRowActionIsInSegmentsTable(): void
    {
        $this->actingAs($this->adminUser());

        $segment = $this->segment();

        Livewire::test(ListBroadcastSegments::class)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertTableActionVisible('request_consent', $segment);
    }

    public function testRequestConsentDispatchesJobForThisSegment(): void
    {
        Queue::fake();
        $this->actingAs($this->adminUser());
        $this->activeDialog(11, 1);

        $segment = $this->segment();

        Livewire::test(ViewBroadcastSegment::class, ['record' => $segment->getKey()])
            ->callAction('request_consent');

        Queue::assertPushed(
            SendConsentRequestsJob::class,
            static fn (SendConsentRequestsJob $job): bool => $job->segmentId === $segment->getKey(),
        );
    }

    public function testRequestConsentDoesNotDispatchWithoutActiveDialogs(): void
    {
        Queue::fake();
        $this->actingAs($this->adminUser());

        Livewire::test(ViewBroadcastSegment::class, ['record' => $this->segment()->getKey()])
            ->callAction('request_consent');

        Queue::assertNotPushed(SendConsentRequestsJob::class);
    }
}
