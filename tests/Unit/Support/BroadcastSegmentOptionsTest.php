<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Support\BroadcastSegmentOptions;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\MaxPhpClient\Enum\ChatType;

class BroadcastSegmentOptionsTest extends TestCase
{
    private function dialog(int $chatId, MaxChatStatus $status = MaxChatStatus::Active): void
    {
        Chats::create($chatId, $chatId, [
            'status' => $status,
            'chat_type' => ChatType::Dialog,
        ]);
    }

    /**
     * @param  list<int>  $chatIds
     */
    private function segment(string $name, array $chatIds): BroadcastSegment
    {
        return BroadcastSegment::query()->create([
            'name' => $name,
            'chat_ids' => $chatIds,
        ]);
    }

    public function testOnlySegmentsWithDialogRecipientsAreListed(): void
    {
        $this->dialog(11);
        $this->dialog(22);
        Chats::create(33, 33, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);

        $vip = $this->segment('VIP', [11, 22]);
        $empty = $this->segment('Пустой', []);
        $groupsOnly = $this->segment('Группы', [33]);

        $options = BroadcastSegmentOptions::options();

        self::assertArrayHasKey($vip->id, $options);
        self::assertArrayNotHasKey($empty->id, $options);
        self::assertArrayNotHasKey($groupsOnly->id, $options);
    }

    public function testLabelShowsRecipientCountBadge(): void
    {
        $this->dialog(11);
        $this->dialog(22);
        $segment = $this->segment('VIP', [11, 22]);

        $label = BroadcastSegmentOptions::options()[$segment->id];

        self::assertStringContainsString('VIP', $label);
        self::assertStringContainsString('fi-badge', $label);
        self::assertStringContainsString('>2</span>', $label);
    }

    public function testInactiveDialogsAreNotCounted(): void
    {
        $this->dialog(11);
        $this->dialog(22, MaxChatStatus::Stopped);
        $segment = $this->segment('VIP', [11, 22]);

        $label = BroadcastSegmentOptions::options()[$segment->id];

        self::assertStringContainsString('>1</span>', $label);
    }

    public function testSegmentNameIsEscaped(): void
    {
        $this->dialog(11);
        $segment = $this->segment('<b>VIP</b>', [11]);

        $label = BroadcastSegmentOptions::options()[$segment->id];

        self::assertStringNotContainsString('<b>VIP</b>', $label);
        self::assertStringContainsString('&lt;b&gt;VIP&lt;/b&gt;', $label);
    }

    public function testLabelsForKeepsSelectedSegmentsWithoutRecipientsAndWithoutBadge(): void
    {
        $empty = $this->segment('Пустой', []);

        $labels = BroadcastSegmentOptions::labelsFor([$empty->id, '999']);

        self::assertSame([$empty->id => 'Пустой'], $labels);
        self::assertStringNotContainsString('fi-badge', $labels[$empty->id]);
    }
}
