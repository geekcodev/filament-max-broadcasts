<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property list<int> $chat_ids
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'description',
    'chat_ids',
    'created_by',
])]
class BroadcastSegment extends Model
{
    public const string TABLE = 'max_broadcast_segments';

    protected $table = self::TABLE;

    protected function casts(): array
    {
        return [
            'chat_ids' => 'array',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function creator(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('filament-max-broadcasts.user_model', \Illuminate\Foundation\Auth\User::class);

        return $this->belongsTo($userModel, 'created_by');
    }

    public function getChatCountAttribute(): int
    {
        return count($this->chat_ids ?? []);
    }
}
