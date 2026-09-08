<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Models;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $segment_id
 * @property int $chat_id
 * @property int|null $user_id
 * @property BroadcastConsentAction $action
 * @property string $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property BroadcastSegment $segment
 * @property MaxChat|null $chat
 */
#[Fillable([
    'segment_id',
    'chat_id',
    'user_id',
    'action',
    'source',
])]
class BroadcastConsent extends Model
{
    public const string TABLE = 'max_broadcast_consents';

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'callback',
    ];

    protected function casts(): array
    {
        return [
            'action' => BroadcastConsentAction::class,
            'chat_id' => 'integer',
            'user_id' => 'integer',
            'source' => 'string',
        ];
    }

    /** @return BelongsTo<BroadcastSegment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(BroadcastSegment::class, 'segment_id');
    }

    /**
     * Чат, из которого получен ответ. Ищем по chat_id (в MAX он глобально
     * уникален для личного диалога), по аналогии с broadcast_recipients.
     *
     * @return BelongsTo<MaxChat, $this>
     */
    public function chat(): BelongsTo
    {
        /** @var class-string<MaxChat> $chatsModel */
        $chatsModel = config('filament-max-broadcasts.chats_model', MaxChat::class);

        return $this->belongsTo($chatsModel, 'chat_id', 'chat_id');
    }
}
