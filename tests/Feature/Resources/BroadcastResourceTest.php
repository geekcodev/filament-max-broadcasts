<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Section;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use GeekCo\FilamentMaxBroadcasts\Jobs\SendBroadcastJob;
use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastRecipient;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Resources\BroadcastResource;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\CreateBroadcast;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ListBroadcasts;
use GeekCo\FilamentMaxBroadcasts\Resources\Pages\ViewBroadcast;
use GeekCo\FilamentMaxBroadcasts\Services\ConsentService;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\ChatType;
use GeekCo\MaxPhpClient\Enum\UploadType;
use Illuminate\Http\UploadedFile;
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
        return Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Chat,
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
                'recipient_chat_ids' => [11],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(BroadcastResource::getUrl('index'));

        /** @var Broadcast $broadcast */
        $broadcast = Broadcast::query()->firstOrFail();

        self::assertSame(BroadcastStatus::Running, $broadcast->status);
        self::assertStringNotContainsString('<script', $broadcast->text);
        self::assertStringContainsString('Hello', $broadcast->text);
        self::assertSame(1, $broadcast->total_recipients);
        self::assertSame(1, $broadcast->recipients()->count());

        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function testCreatePageUsesOutlinedActionsWithIconsAndWithoutCreateAnother(): void
    {
        $this->actingAs($this->adminUser());

        /** @var CreateBroadcast $page */
        $page = Livewire::test(CreateBroadcast::class)->instance();

        $actions = $this->formActions($page);

        self::assertArrayNotHasKey('createAnother', $actions);
        self::assertArrayHasKey('create', $actions);

        self::assertTrue($actions['create']->isOutlined());
        self::assertSame('heroicon-o-plus', $actions['create']->getIcon());

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

    public function testCreateBroadcastAcceptsWhitelistedFileType(): void
    {
        Storage::fake('local');

        $this->actingAs($this->adminUser());
        $this->activeChat();

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'type' => 'news',
                'text' => 'Body',
                'recipient_chat_ids' => [11],
                'files' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var Broadcast $broadcast */
        $broadcast = Broadcast::query()->firstOrFail();
        $attachment = $broadcast->attachments()->firstOrFail();

        self::assertSame(UploadType::File, $attachment->upload_type);
        self::assertStringEndsWith('.pdf', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function testAttachmentsFileFieldUsesConfiguredWhitelist(): void
    {
        $this->actingAs($this->adminUser());

        /** @var list<string> $expected */
        $expected = array_values((array) config('filament-max-broadcasts.image.accepted_file_types'));

        self::assertContains('application/pdf', $expected);
        self::assertNotContains('image/png', $expected);

        Livewire::test(CreateBroadcast::class)
            ->assertFormFieldExists('files', static function (FileUpload $field) use ($expected): bool {
                return $field->getAcceptedFileTypes() === $expected;
            });
    }

    public function testCreateBroadcastIsForbiddenWithoutCreatePermission(): void
    {
        $user = $this->userWithoutBroadcastRights();
        $user->forceFill(['can_view_broadcasts' => true])->save();
        $this->actingAs($user);

        Livewire::test(CreateBroadcast::class)->assertForbidden();
    }

    public function testCreateActionIsVisibleWithCreatePermission(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcasts::class)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertActionVisible('create')
            // @phpstan-ignore method.notFound
            ->assertActionExists('create', static fn (Action $action): bool => $action->isOutlined());
    }

    public function testCreateActionIsHiddenWithoutCreatePermission(): void
    {
        $user = $this->userWithoutBroadcastRights();
        $user->forceFill(['can_view_broadcasts' => true])->save();
        $this->actingAs($user);

        Livewire::test(ListBroadcasts::class)
            ->assertSuccessful()
            // @phpstan-ignore method.notFound
            ->assertActionHidden('create');
    }

    public function testCreatePageIsForbiddenWithoutCreatePermission(): void
    {
        $user = $this->userWithoutBroadcastRights();
        $user->forceFill(['can_view_broadcasts' => true])->save();
        $this->actingAs($user);

        $this->get(BroadcastResource::getUrl('create'))->assertForbidden();
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

    public function testBroadcastFormUsesSingleFullWidthColumn(): void
    {
        $broadcast = $this->broadcast(BroadcastStatus::Completed);
        $this->actingAs($this->adminUser());

        /** @var CreateBroadcast $createPage */
        $createPage = Livewire::test(CreateBroadcast::class)->instance();
        /** @var ViewBroadcast $viewPage */
        $viewPage = Livewire::test(ViewBroadcast::class, ['record' => $broadcast->getKey()])->instance();

        self::assertSame(1, $createPage->getSchema('form')?->getColumns('lg'));
        self::assertSame(1, $viewPage->getSchema('form')?->getColumns('lg'));

        $statsSection = null;

        foreach ($viewPage->getSchema('form')->getComponents() as $component) {
            if (
                $component instanceof Section
                && $component->getHeading() === __('filament-max-broadcasts::broadcasts.form.stats_section')
            ) {
                $statsSection = $component;
            }
        }

        self::assertInstanceOf(Section::class, $statsSection);
        self::assertSame(4, $statsSection->getColumns('lg'));
    }

    public function testMessageEditorUsesCustomMinHeight(): void
    {
        $this->actingAs($this->adminUser());

        /** @var CreateBroadcast $createPage */
        $createPage = Livewire::test(CreateBroadcast::class)->instance();

        $editor = null;

        foreach ($createPage->getSchema('form')?->getFlatComponents(withActions: false) ?? [] as $component) {
            if ($component instanceof RichEditor && $component->getName() === 'text') {
                $editor = $component;
            }
        }

        self::assertInstanceOf(RichEditor::class, $editor);
        self::assertSame('18rem', $editor->getMinHeight());
    }

    public function testViewPageShowsImagePreviewAndLinksForOtherFiles(): void
    {
        Storage::fake('public');
        config()->set('filament-max-broadcasts.image.disk', 'public');

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

    public function testViewPageServesPrivateAttachmentsByTemporaryUrls(): void
    {
        Storage::disk('local')->put('broadcasts/photo.png', 'fake');
        Storage::disk('local')->put('broadcasts/doc.txt', 'fake');

        try {
            $broadcast = $this->broadcast(BroadcastStatus::Completed);
            $broadcast->attachments()->createMany([
                ['upload_type' => UploadType::Image->value, 'path' => 'broadcasts/photo.png', 'sort_order' => 0],
                ['upload_type' => UploadType::File->value, 'path' => 'broadcasts/doc.txt', 'sort_order' => 1],
            ]);
            $this->actingAs($this->adminUser());

            $html = $this->get(BroadcastResource::getUrl('view', ['record' => $broadcast]))
                ->assertSuccessful()
                ->getContent();

            self::assertIsString($html);

            self::assertStringContainsString('<img ', $html);
            self::assertMatchesRegularExpression(
                '/src="[^"]*\/storage\/broadcasts\/photo\.png\?[^"]*signature/',
                $html,
            );
            self::assertMatchesRegularExpression(
                '/href="[^"]*\/storage\/broadcasts\/doc\.txt\?[^"]*signature/',
                $html,
            );
        } finally {
            Storage::disk('local')->delete(['broadcasts/photo.png', 'broadcasts/doc.txt']);
        }
    }

    public function testViewPageShowsFileNameWithoutLinkWhenFileIsMissing(): void
    {
        $broadcast = $this->broadcast(BroadcastStatus::Completed);
        $broadcast->attachments()->createMany([
            ['upload_type' => UploadType::Image->value, 'path' => 'broadcasts/missing.png', 'sort_order' => 0],
            ['upload_type' => UploadType::File->value, 'path' => 'broadcasts/missing.pdf', 'sort_order' => 1],
        ]);
        $this->actingAs($this->adminUser());

        $html = $this->get(BroadcastResource::getUrl('view', ['record' => $broadcast]))
            ->assertSuccessful()
            ->getContent();

        self::assertIsString($html);
        self::assertStringNotContainsString('<img ', $html);
        self::assertStringContainsString('missing.pdf', $html);
        self::assertStringNotContainsString('broadcasts/missing', $html);
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

    public function testSendNowDoesNothingForNonScheduledBroadcast(): void
    {
        $broadcast = $this->broadcast(BroadcastStatus::Completed);
        $this->actingAs($this->adminUser());

        $page = Livewire::test(ViewBroadcast::class, ['record' => $broadcast->getKey()])->instance();

        $method = new \ReflectionMethod($page, 'sendNow');
        $method->invoke($page);

        self::assertSame(BroadcastStatus::Completed, $broadcast->refresh()->status);
        Queue::assertNotPushed(SendBroadcastJob::class);
    }

    public function testCancelDoesNothingForFinishedBroadcast(): void
    {
        $broadcast = $this->broadcast(BroadcastStatus::Completed);
        $this->actingAs($this->adminUser());

        $page = Livewire::test(ViewBroadcast::class, ['record' => $broadcast->getKey()])->instance();

        $method = new \ReflectionMethod($page, 'cancel');
        $method->invoke($page);

        self::assertSame(BroadcastStatus::Completed, $broadcast->refresh()->status);
    }

    public function testCreateDefaultsTypeWhenPayloadOmitsIt(): void
    {
        $this->actingAs($this->adminUser());
        $this->activeChat();

        $page = Livewire::test(CreateBroadcast::class)->instance();

        $method = new \ReflectionMethod($page, 'handleRecordCreation');

        /** @var Broadcast $broadcast */
        $broadcast = $method->invoke($page, [
            'text' => 'Hello',
            'recipient_chat_ids' => [11],
        ]);

        self::assertSame('news', $broadcast->type);
        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function testCreateAcceptsScheduledAtFromPayload(): void
    {
        $this->actingAs($this->adminUser());
        $this->activeChat();

        $scheduledAt = now()->addHour()->startOfSecond();
        $page = Livewire::test(CreateBroadcast::class)->instance();

        $method = new \ReflectionMethod($page, 'handleRecordCreation');

        /** @var Broadcast $broadcast */
        $broadcast = $method->invoke($page, [
            'text' => 'Scheduled',
            'type' => 'news',
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'recipient_chat_ids' => [11],
        ]);

        self::assertSame($scheduledAt->toDateTimeString(), $broadcast->scheduled_at?->toDateTimeString());
        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function testRepeatActionCreatesNewBroadcast(): void
    {
        $this->activeChat();
        $original = $this->broadcast(BroadcastStatus::Completed, ['recipient_chat_ids' => [11]]);
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
        $this->activeChat();
        $original = $this->broadcast(BroadcastStatus::Completed, ['recipient_chat_ids' => [11]]);
        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcasts::class)
            ->callTableAction('repeat', $original);

        self::assertSame(2, Broadcast::query()->count());

        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function testRepeatActionRespectsFreshOptOut(): void
    {
        Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);
        Chats::create(22, 2, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);

        $segment = BroadcastSegment::query()->create(['name' => 'VIP', 'chat_ids' => [11, 22]]);
        $consent = app(ConsentService::class);
        $consent->optIn(11, 1, $segment->id);
        $consent->optIn(22, 2, $segment->id);

        $original = $this->broadcast(BroadcastStatus::Completed, ['recipient_chat_ids' => [11, 22]]);
        $original->segments()->attach($segment->id);
        $original->recipients()->createMany([
            ['user_id' => 1, 'chat_id' => 11, 'status' => BroadcastRecipientStatus::Sent],
            ['user_id' => 2, 'chat_id' => 22, 'status' => BroadcastRecipientStatus::Sent],
        ]);

        $consent->optOut(11, 1, $segment->id);

        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcasts::class)
            ->callTableAction('repeat', $original);

        /** @var Broadcast $repeated */
        $repeated = Broadcast::query()->latest('id')->firstOrFail();

        self::assertSame([22], $repeated->recipients->pluck('chat_id')->all());
        self::assertSame([22], $repeated->recipient_chat_ids);
        self::assertSame([$segment->id], $repeated->segments->pluck('id')->all());
    }

    public function testRepeatWithoutRecipientsIsRejected(): void
    {
        $original = $this->broadcast(BroadcastStatus::Completed);
        $this->actingAs($this->adminUser());

        Livewire::test(ViewBroadcast::class, ['record' => $original->getKey()])
            ->callAction('repeat');

        self::assertSame(1, Broadcast::query()->count());

        Queue::assertNotPushed(SendBroadcastJob::class);
    }

    public function testCreateBroadcastRequiresSegmentsOrRecipients(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'type' => 'news',
                'text' => 'Hello',
            ])
            ->call('create')
            // @phpstan-ignore method.notFound
            ->assertHasFormErrors(['segment_ids', 'recipient_chat_ids']);

        Queue::assertNothingPushed();
    }

    public function testCreateBroadcastWithSegmentOnlyPassesValidation(): void
    {
        $this->actingAs($this->adminUser());

        Chats::create(11, 1, [
            'status' => MaxChatStatus::Active,
            'chat_type' => ChatType::Dialog,
        ]);
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);
        /** @var int $segmentId */
        $segmentId = $segment->getKey();
        app(ConsentService::class)->optIn(11, 1, $segmentId);

        Livewire::test(CreateBroadcast::class)
            ->fillForm([
                'type' => 'news',
                'text' => 'Hello',
                'segment_ids' => [$segment->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function testViewPageShowsStartedSegmentsOrPlaceholder(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'VIP',
            'chat_ids' => [11],
        ]);
        $started = $this->broadcast(BroadcastStatus::Completed);
        $started->segments()->attach($segment->id);
        $this->actingAs($this->adminUser());

        $this->get(BroadcastResource::getUrl('view', ['record' => $started]))
            ->assertSuccessful()
            ->assertSee('VIP')
            ->assertDontSee(__('filament-max-broadcasts::broadcasts.form.no_segment'));

        $withoutSegments = $this->broadcast(BroadcastStatus::Completed);

        $this->get(BroadcastResource::getUrl('view', ['record' => $withoutSegments]))
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.form.no_segment'));
    }

    public function testListTableShowsCancelledBroadcastWithAttachment(): void
    {
        Storage::fake('public');
        config()->set('filament-max-broadcasts.image.disk', 'public');
        Storage::disk('public')->put('images/photo.png', 'fake');

        $broadcast = $this->broadcast(BroadcastStatus::Cancelled);
        $broadcast->attachments()->createMany([
            ['upload_type' => UploadType::Image->value, 'path' => 'images/photo.png', 'sort_order' => 0],
        ]);
        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcasts::class)
            ->assertSuccessful()
            ->assertSee(__('filament-max-broadcasts::broadcasts.table.has_image'))
            ->assertSee(BroadcastStatus::Cancelled->label());
    }

    public function testRepeatActionFromTableRejectsBroadcastWithoutRecipients(): void
    {
        $original = $this->broadcast(BroadcastStatus::Completed);
        $this->actingAs($this->adminUser());

        Livewire::test(ListBroadcasts::class)
            ->callTableAction('repeat', $original);

        self::assertSame(1, Broadcast::query()->count());
        Queue::assertNotPushed(SendBroadcastJob::class);
    }
}
