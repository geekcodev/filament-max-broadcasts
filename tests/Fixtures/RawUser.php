<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Fixtures;

use GeekCo\LaravelMaxClient\Models\MaxUser;

/**
 * Пользователь MAX без приведений и без обращения к БД.
 *
 * Парный к `RawChat`: нужен, чтобы проверить цепочки имени собеседника
 * (`name`, `first_name` + `last_name`, `username`) в обход реестра чатов.
 */
/**
 * Атрибуты ставятся принудительно (`forceFill`): у моделей ядра разные `$fillable`
 * на 1.1 и 1.2, а стаб должен принимать одинаковый набор значений в обоих.
 */
final class RawUser extends MaxUser
{
    protected $table = 'max_users';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct();

        $this->forceFill($attributes);
    }
}
