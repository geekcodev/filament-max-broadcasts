<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Models;

use GeekCo\MaxPhpClient\Enum\UploadType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $broadcast_id
 * @property \GeekCo\MaxPhpClient\Enum\UploadType $upload_type
 * @property string $path
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
#[Fillable([
    'broadcast_id',
    'upload_type',
    'path',
    'sort_order',
])]
class BroadcastAttachment extends Model
{
    public const string TABLE = 'max_broadcast_attachments';

    protected $table = self::TABLE;

    protected function casts(): array
    {
        return [
            'upload_type' => UploadType::class,
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Broadcast, $this> */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }
}
