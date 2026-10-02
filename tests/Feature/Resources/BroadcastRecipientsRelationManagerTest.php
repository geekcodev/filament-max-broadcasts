<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastRecipient;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ViewBroadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\RelationManagers\BroadcastRecipientsRelationManager;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxUser;
use Livewire\Livewire;

class BroadcastRecipientsRelationManagerTest extends TestCase
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

    private function broadcast(): Broadcast
    {
        return Broadcast::query()->create([
            'text' => 'Body',
            'type' => 'news',
            'status' => BroadcastStatus::Completed,
            'total_recipients' => 3,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recipient(Broadcast $broadcast, array $attributes = []): BroadcastRecipient
    {
        return BroadcastRecipient::query()->create(array_merge([
            'broadcast_id' => $broadcast->id,
            'chat_id' => 11,
            'user_id' => 1,
            'status' => BroadcastRecipientStatus::Pending,
        ], $attributes));
    }

    /**
     * @return \Livewire\Features\SupportTesting\Testable<\Livewire\Component>
     */
    private function relationManager(Broadcast $broadcast)
    {
        return Livewire::test(BroadcastRecipientsRelationManager::class, [
            'ownerRecord' => $broadcast,
            'pageClass' => ViewBroadcast::class,
        ]);
    }

    public function testTitleIsLocalized(): void
    {
        $broadcast = $this->broadcast();

        $title = BroadcastRecipientsRelationManager::getTitle($broadcast, ViewBroadcast::class);

        self::assertSame(__('filament-max-broadcasts::broadcasts.recipients.title'), $title);
        self::assertNotSame('', $title);
    }

    public function testTableListsRecipientsWithColumnsAndFilters(): void
    {
        $this->actingAs($this->adminUser());

        $broadcast = $this->broadcast();
        $pending = $this->recipient($broadcast);
        $sent = $this->recipient($broadcast, [
            'chat_id' => 22,
            'user_id' => 2,
            'status' => BroadcastRecipientStatus::Sent,
            'sent_at' => now(),
            'error' => 'ошибка доставки',
        ]);
        $failed = $this->recipient($broadcast, [
            'chat_id' => 33,
            'user_id' => 3,
            'status' => BroadcastRecipientStatus::Failed,
        ]);

        $this->relationManager($broadcast)
            ->assertCanSeeTableRecords([$pending, $sent, $failed])
            ->assertTableFilterExists('status')
            ->assertTableColumnExists('name')
            ->assertTableColumnExists('user_id')
            ->assertTableColumnExists('chat_id')
            ->assertTableColumnExists('status')
            ->assertTableColumnExists('error')
            ->assertTableColumnExists('sent_at')
            ->assertCanRenderTableColumn('name')
            ->assertSuccessful();
    }

    public function testNameColumnFallsBackToAnonymousLabelWithoutUser(): void
    {
        $this->actingAs($this->adminUser());

        $broadcast = $this->broadcast();
        $recipient = $this->recipient($broadcast, ['user_id' => 777]);

        $this->relationManager($broadcast)
            ->assertTableColumnFormattedStateSet(
                'name',
                __('filament-max-broadcasts::broadcasts.recipients.anonymous', ['id' => 777]),
                $recipient,
            );
    }

    public function testNameColumnShowsUserFullName(): void
    {
        $this->actingAs($this->adminUser());

        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'username' => 'ivan_petrov',
        ]);
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
        ]);

        $broadcast = $this->broadcast();
        $recipient = $this->recipient($broadcast);

        $this->relationManager($broadcast)
            ->assertTableColumnFormattedStateSet('name', 'Иван Петров', $recipient);
    }

    public function testNameColumnFallsBackWhenUserHasNoNames(): void
    {
        $this->actingAs($this->adminUser());

        MaxUser::query()->create(['user_id' => 1, 'first_name' => '  ']);
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
        ]);

        $broadcast = $this->broadcast();
        $recipient = $this->recipient($broadcast, ['user_id' => 1]);

        $this->relationManager($broadcast)
            ->assertTableColumnFormattedStateSet(
                'name',
                __('filament-max-broadcasts::broadcasts.recipients.anonymous', ['id' => 1]),
                $recipient,
            );
    }

    public function testStatusColumnFormatsEnumLabel(): void
    {
        $this->actingAs($this->adminUser());

        $broadcast = $this->broadcast();
        $sent = $this->recipient($broadcast, ['status' => BroadcastRecipientStatus::Sent]);

        $this->relationManager($broadcast)
            ->assertTableColumnFormattedStateSet(
                'status',
                BroadcastRecipientStatus::Sent->label(),
                $sent,
            );
    }

    public function testSearchTableFiltersRecipientsByUserName(): void
    {
        $this->actingAs($this->adminUser());

        MaxUser::query()->create([
            'user_id' => 1,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
        ]);
        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
        ]);

        $broadcast = $this->broadcast();
        $matched = $this->recipient($broadcast);
        $other = $this->recipient($broadcast, ['chat_id' => 22, 'user_id' => 2]);

        $this->relationManager($broadcast)
            ->searchTable('Иван')
            ->assertCanSeeTableRecords([$matched])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function testStatusFilterNarrowsRecipients(): void
    {
        $this->actingAs($this->adminUser());

        $broadcast = $this->broadcast();
        $failed = $this->recipient($broadcast, ['status' => BroadcastRecipientStatus::Failed]);
        $pending = $this->recipient($broadcast, ['chat_id' => 22, 'user_id' => 2]);

        $this->relationManager($broadcast)
            ->filterTable('status', BroadcastRecipientStatus::Failed)
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$pending]);
    }
}
