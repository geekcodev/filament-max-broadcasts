<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Models;

use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\TestUser;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class BroadcastSegmentTest extends TestCase
{
    public function testCreatesSegmentWithChatIdsCastToArray(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'Active users',
            'chat_ids' => [11, 22, 33],
        ]);

        self::assertSame('Active users', $segment->name);
        self::assertSame([11, 22, 33], $segment->chat_ids);
        self::assertSame(3, $segment->chat_count);
    }

    public function testChatCountAttribute(): void
    {
        $segment = BroadcastSegment::query()->create([
            'name' => 'Empty',
            'chat_ids' => [],
        ]);

        self::assertSame(0, $segment->chat_count);
    }

    public function testStoresCreator(): void
    {
        $user = TestUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);

        $segment = BroadcastSegment::query()->create([
            'name' => 'Mine',
            'chat_ids' => [11],
            'created_by' => $user->id,
        ]);

        self::assertSame($user->id, $segment->created_by);
        self::assertNotNull($segment->creator);
    }
}
