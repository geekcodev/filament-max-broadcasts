<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Models;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $text
 * @property string $type
 * @property BroadcastStatus $status
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property int $total_recipients
 * @property int $delivered_count
 * @property int $failed_count
 * @property int|null $created_by
 * @property list<int>|null $recipient_chat_ids
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int, BroadcastAttachment> $attachments
 * @property \Illuminate\Database\Eloquent\Collection<int, BroadcastRecipient> $recipients
 * @property \Illuminate\Database\Eloquent\Collection<int, BroadcastSegment> $segments
 */
#[Fillable([
    'text',
    'type',
    'status',
    'scheduled_at',
    'sent_at',
    'total_recipients',
    'delivered_count',
    'failed_count',
    'created_by',
    'recipient_chat_ids',
])]
class Broadcast extends Model
{
    public const string TABLE = 'max_broadcasts';

    protected $table = self::TABLE;

    protected function casts(): array
    {
        return [
            'status' => BroadcastStatus::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'total_recipients' => 'integer',
            'delivered_count' => 'integer',
            'failed_count' => 'integer',
            'recipient_chat_ids' => 'array',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function creator(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('filament-max-broadcasts.user_model', \Illuminate\Foundation\Auth\User::class);

        return $this->belongsTo($userModel, 'created_by');
    }

    /** @return HasMany<BroadcastRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    /** @return BelongsToMany<BroadcastSegment, $this> */
    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(
            BroadcastSegment::class,
            'max_broadcast_segment',
            'broadcast_id',
            'segment_id',
        );
    }

    /** @return HasMany<BroadcastAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(BroadcastAttachment::class)->orderBy('sort_order');
    }
}
