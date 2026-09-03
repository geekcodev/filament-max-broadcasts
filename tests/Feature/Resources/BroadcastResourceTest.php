<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendBroadcastJob;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastRecipient;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastResource;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcasts;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ViewBroadcast;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\UploadType;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class BroadcastResourceTest extends TestCase
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

    private function userWithoutBroadcastRights(): TestUser
    {
        return TestUser::query()->create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'password' => 'secret',
        ]);
    }

    private function activeChat(): MaxChat
    {
        return MaxChat::query()->create([
            'user_id' => 1,
            'chat_id' => 11,
            'status' => MaxChatStatus::Active,
        ]);
    }

    public function testIndexPageIsForbiddenWithoutPermission(): void
    {
        $this->actingAs($this->userWithoutBroadcastRights());

        $this->get(BroadcastResource::getUrl('index'))->assertForbidden();
    }

    public function testIndexPageIsAccessibleWithPermission(): void
    {
        $this->actingAs($this->adminUser());

        $this->get(BroadcastResource::getUrl('index'))->assertSuccessful();
    }

    public function testCreateBroadcastThroughForm(): void
    {
        $this->actingAs($this->adminUser());
        $this->activeChat();

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'type' => 'news',
                'text' => 'Hello <script>alert(1)</script><b>world</b>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var Broadcast $broadcast */
        $broadcast = Broadcast::query()->firstOrFail();

        self::assertSame(BroadcastStatus::Running, $broadcast->status);
        self::assertStringNotContainsString('<script', $broadcast->text);
        self::assertStringContainsString('Hello', $broadcast->text);
        self::assertSame(1, $broadcast->total_recipients);
        self::assertSame(1, $broadcast->recipients()->count());

        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function testCreateBroadcastIsForbiddenWithoutCreatePermission(): void
    {
        $user = $this->userWithoutBroadcastRights();
        $user->forceFill(['can_view_broadcasts' => true])->save();
        $this->actingAs($user);

        Livewire::test(CreateBroadcast::class)->assertForbidden();
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function broadcast(BroadcastStatus $status, array $extra = []): Broadcast
    {
        return Broadcast::query()->create(array_merge([
            'text' => 'Body',
            'type' => 'news',
            'status' => $status,
            'total_recipients' => 1,
        ], $extra));
    }

    public function testViewPageIsAccessible(): void
    {
        $broadcast = $this->broadcast(BroadcastStatus::Completed);
        $this->actingAs($this->adminUser());

        $this->get(BroadcastResource::getUrl('view', ['record' => $broadcast]))->assertSuccessful();
    }

    public function testViewPageIsForbiddenWithoutViewPermission(): void
    {
        $broadcast = $this->broadcast(BroadcastStatus::Completed);
        $this->actingAs($this->userWithoutBroadcastRights());

        $this->get(BroadcastResource::getUrl('view', ['record' => $broadcast]))->assertForbidden();
    }

    public function testViewPageShowsImagePreviewAndLinksForOtherFiles(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('images/photo.png', 'fake');
        Storage::disk('public')->put('files/doc.txt', 'fake');

        $broadcast = $this->broadcast(BroadcastStatus::Completed);
        $broadcast->attachments()->createMany([
            ['upload_type' => UploadType::Image->value, 'path' => 'images/photo.png', 'sort_order' => 0],
            ['upload_type' => UploadType::File->value, 'path' => 'files/doc.txt', 'sort_order' => 1],
        ]);
        $this->actingAs($this->adminUser());

        $imageUrl = trim((string) Storage::disk('public')->url('images/photo.png'));
        $fileUrl = trim((string) Storage::disk('public')->url('files/doc.txt'));

        self::assertNotSame('', $imageUrl);
        self::assertNotSame('', $fileUrl);

        $html = $this->get(BroadcastResource::getUrl('view', ['record' => $broadcast]))
            ->assertSuccessful()
            ->getContent();

        self::assertIsString($html);

        self::assertStringContainsString('<img ', $html);
        self::assertStringContainsString('src="'.\e($imageUrl).'"', $html);
        self::assertStringContainsString('href="'.\e($fileUrl).'"', $html);
    }

    public function testSendNowAction(): void
    {
        $broadcast = $this->broadcast(BroadcastStatus::Scheduled, [
            'scheduled_at' => now()->addHour(),
        ]);
        $this->actingAs($this->adminUser());

        Livewire::test(ViewBroadcast::class, ['record' => $broadcast->getKey()])
            ->callAction('sendNow');

        $broadcast->refresh();

        self::assertSame(BroadcastStatus::Running, $broadcast->status);
        self::assertNull($broadcast->scheduled_at);

        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function testCancelAction(): void
    {
        $broadcast = $this->broadcast(BroadcastStatus::Scheduled, [
            'scheduled_at' => now()->addHour(),
        ]);
        $this->actingAs($this->adminUser());

        Livewire::test(ViewBroadcast::class, ['record' => $broadcast->getKey()])
            ->callAction('cancel');

        $broadcast->refresh();

        self::assertSame(BroadcastStatus::Cancelled, $broadcast->status);
    }

    public function testRepeatActionCreatesNewBroadcast(): void
    {
        $original = $this->broadcast(BroadcastStatus::Completed);
        $this->actingAs($this->adminUser());

        Livewire::test(ViewBroadcast::class, ['record' => $original->getKey()])
            ->callAction('repeat');

        $newBroadcasts = Broadcast::query()
            ->where('id', '!=', $original->id)
            ->get();

        self::assertCount(1, $newBroadcasts);
        self::assertNotNull($newBroadcasts->first());
        self::assertSame(BroadcastStatus::Running, $newBroadcasts->first()->status);
        self::assertSame($original->text, $newBroadcasts->first()->text);

        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function testDeleteActionRemovesBroadcast(): void
    {
        $broadcast = $this->broadcast(BroadcastStatus::Completed);
        BroadcastRecipient::query()->create([
            'broadcast_id' => $broadcast->id,
            'chat_id' => 11,
            'user_id' => 1,
            'status' => 'sent',
        ]);
        $this->actingAs($this->adminUser());

        Livewire::test(ViewBroadcast::class, ['record' => $broadcast->getKey()])
            ->callAction('removeBroadcast');

        self::assertSame(0, Broadcast::query()->count());
        self::assertSame(0, BroadcastRecipient::query()->count());

        self::assertSame(0, Broadcast::query()->count());
        self::assertSame(0, BroadcastRecipient::query()->count());
    }

    public function testListTableShowsBroadcasts(): void
    {
        $this->broadcast(BroadcastStatus::Completed);
        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcasts::class)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertCanSeeTableRecords(Broadcast::query()->get());
    }

    public function testRepeatActionFromTable(): void
    {
        $original = $this->broadcast(BroadcastStatus::Completed);
        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcasts::class)
            ->callTableAction('repeat', $original);

        self::assertSame(2, Broadcast::query()->count());

        Queue::assertPushed(SendBroadcastJob::class);
    }
}
