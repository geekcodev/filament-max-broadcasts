<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $name
 * @property string $email
 * @property bool $can_view_broadcasts
 * @property bool $can_create_broadcasts
 * @property bool $can_manage_broadcasts
 */
class TestUser extends BaseUser
{
    use Notifiable;

    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'can_view_broadcasts',
        'can_create_broadcasts',
        'can_manage_broadcasts',
    ];

    protected function casts(): array
    {
        return [
            'can_view_broadcasts' => 'boolean',
            'can_create_broadcasts' => 'boolean',
            'can_manage_broadcasts' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
