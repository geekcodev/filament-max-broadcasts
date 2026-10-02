<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Fixtures;

use GeekCo\LaravelMaxClient\Models\MaxChat;

/**
 * Чат без приведений и без обращения к БД.
 *
 * Нужен для значений, которых в реестре нет и которые поэтому нельзя получить
 * через фикстуру `Chats`: `chat_id` числовой строкой или нечисловой, строковый
 * `chat_type`. Форма реестра (gotcha 1) значения не меняет, поэтому стаб работает
 * на обеих версиях `laravel-max-client`.
 */
/**
 * Атрибуты ставятся принудительно (`forceFill`): у моделей ядра разные `$fillable`
 * на 1.1 и 1.2, а стаб должен принимать одинаковый набор значений в обоих.
 */
final class RawChat extends MaxChat
{
    protected $table = 'max_chats';

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
