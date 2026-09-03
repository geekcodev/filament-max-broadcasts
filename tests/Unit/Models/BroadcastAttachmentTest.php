<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Models;

use GeekCo\FilamentMaxBroadcasts\Models\Broadcast;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastAttachment;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\MaxPhpClient\Enum\UploadType;

class BroadcastAttachmentTest extends TestCase
{
    public function testUsesMaxBroadcastAttachmentsTable(): void
    {
        self::assertSame('max_broadcast_attachments', (new BroadcastAttachment())->getTable());
    }

    public function testCasts(): void
    {
        $broadcast = Broadcast::query()->create([
            'text' => 'Hello',
            'type' => 'news',
            'status' => 'running',
        ]);

        $attachment = BroadcastAttachment::query()->create([
            'broadcast_id' => $broadcast->id,
            'upload_type' => 'image',
            'path' => 'broadcasts/photo.jpg',
            'sort_order' => 0,
        ]);

        self::assertSame(UploadType::Image, $attachment->upload_type);
        self::assertSame(0, $attachment->sort_order);
    }

    public function testBroadcastRelation(): void
    {
        $broadcast = Broadcast::query()->create([
            'text' => 'Hello',
            'type' => 'news',
            'status' => 'running',
        ]);

        $attachment = BroadcastAttachment::query()->create([
            'broadcast_id' => $broadcast->id,
            'upload_type' => 'video',
            'path' => 'broadcasts/clip.mp4',
            'sort_order' => 0,
        ]);

        $broadcastId = $broadcast->id;
        $related = $attachment->broadcast;
        self::assertNotNull($related);
        self::assertSame($broadcastId, $related->id);
    }
}
