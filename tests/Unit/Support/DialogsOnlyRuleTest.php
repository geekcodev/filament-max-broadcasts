<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Support\DialogsOnlyRule;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\Chats;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Support\Facades\Validator;

class DialogsOnlyRuleTest extends TestCase
{
    public function testAcceptsRegisteredDialogs(): void
    {
        Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);

        $validator = Validator::make(['chat_ids' => [11]], ['chat_ids' => [new DialogsOnlyRule()]]);

        self::assertTrue($validator->passes());
    }

    public function testRejectsNonDialogChats(): void
    {
        Chats::create(11, 1, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Dialog]);
        Chats::create(22, 22, ['status' => MaxChatStatus::Active, 'chat_type' => ChatType::Chat]);

        $validator = Validator::make(['chat_ids' => [11, 22]], ['chat_ids' => [new DialogsOnlyRule()]]);

        self::assertTrue($validator->fails());
        self::assertSame(
            __('filament-max-broadcasts::broadcasts.segment.form.dialogs_only_error'),
            $validator->errors()->first('chat_ids'),
        );
    }

    public function testIgnoresNonArrayValues(): void
    {
        $validator = Validator::make(['chat_ids' => null], ['chat_ids' => [new DialogsOnlyRule()]]);

        self::assertTrue($validator->passes());
    }
}
