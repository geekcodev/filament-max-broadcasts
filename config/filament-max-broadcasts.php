<?php

declare(strict_types=1);

return [

    // Права (проверяются через $user->can(...) — совместимо со spatie/laravel-permission и Gate).
    'permissions' => [
        'view'   => env('FILAMENT_MAX_BROADCASTS_PERMISSION_VIEW', 'broadcasts.view'),
        'create' => env('FILAMENT_MAX_BROADCASTS_PERMISSION_CREATE', 'broadcasts.create'),
        'send'   => env('FILAMENT_MAX_BROADCASTS_PERMISSION_SEND', 'broadcasts.send'),
        'manage' => env('FILAMENT_MAX_BROADCASTS_PERMISSION_MANAGE', 'broadcasts.manage'),
    ],

    // Модели.
    'broadcast_model' => GeekCo\FilamentMaxBroadcasts\Models\Broadcast::class,
    'recipient_model' => GeekCo\FilamentMaxBroadcasts\Models\BroadcastRecipient::class,
    'chats_model'     => GeekCo\LaravelMaxClient\Models\MaxChat::class,
    'user_model'      => env('FILAMENT_MAX_BROADCASTS_USER_MODEL', \Illuminate\Foundation\Auth\User::class),

    // Кнопки-диплинки акций (мини-приложение). По умолчанию пусто — кнопок нет.
    'bot_username' => env('FILAMENT_MAX_BROADCASTS_BOT_USERNAME', ''),
    'promo_buttons' => [
        ['text' => 'Запись на сервис', 'startapp' => 'booking'],
        ['text' => 'Консультация',     'startapp' => 'consult'],
    ],

    // Очередь / отправка.
    'queue' => [
        'batch_size'         => 25,
        'lock_ttl_seconds'   => 600,
        'tries'              => 3,
        'timeout'            => 3600,
        'backoff'            => [60, 300],
    ],

    // Исходящая картинка рассылки.
    'image' => [
        'disk'      => env('FILAMENT_MAX_BROADCASTS_IMAGE_DISK', 'public'),
        'directory' => env('FILAMENT_MAX_BROADCASTS_IMAGE_DIRECTORY', 'broadcasts'),
        'max_kb'    => (int) env('FILAMENT_MAX_BROADCASTS_IMAGE_MAX_KB', 10240),
    ],

    // Получатели.
    'recipients' => [
        'resolver' => GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver::class,
    ],

    // UI ресурса.
    'ui' => [
        'navigation_group' => null,
        'navigation_icon'  => 'heroicon-o-megaphone',
        'navigation_sort'  => 3,
        'navigation_label' => null,
        'label'            => 'Рассылка',
        'plural_label'     => 'Рассылки',
        'slug'             => 'broadcasts',
    ],
];
