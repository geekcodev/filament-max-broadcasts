<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Models;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastRecipientStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $broadcast_id
 * @property int|null $user_id
 * @property int|null $chat_id
 * @property BroadcastRecipientStatus $status
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
#[Fillable([
    'broadcast_id',
    'user_id',
    'chat_id',
    'status',
    'error',
    'sent_at',
])]
class BroadcastRecipient extends Model
{
    public const string TABLE = 'max_broadcast_recipients';

    protected $table = self::TABLE;

    protected function casts(): array
    {
        return [
            'status' => BroadcastRecipientStatus::class,
            'user_id' => 'integer',
            'chat_id' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Broadcast, $this> */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    /**
     * Чат целевого пользователя. Ищем по chat_id: в MAX он глобально уникален
     * для личного диалога, и eager load по нему не раздувается (в отличие от
     * user_id, на который в max_chats приходится много строк). Получатели всегда
     * создаются с chat_id (см. BroadcastService).
     *
     * @return BelongsTo<MaxChat, $this>
     */
    public function maxChat(): BelongsTo
    {
        return $this->belongsTo(MaxChat::class, 'chat_id', 'chat_id');
    }
}
