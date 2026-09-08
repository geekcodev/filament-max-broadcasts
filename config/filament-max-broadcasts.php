<?php

declare(strict_types=1);

return [

    // Права (проверяются через $user->can(...) — совместимо со spatie/laravel-permission и Gate).
    'permissions' => [
        'view'   => env('FILAMENT_MAX_BROADCASTS_PERMISSION_VIEW', 'broadcasts.view'),
        'create' => env('FILAMENT_MAX_BROADCASTS_PERMISSION_CREATE', 'broadcasts.create'),
        'manage' => env('FILAMENT_MAX_BROADCASTS_PERMISSION_MANAGE', 'broadcasts.manage'),
    ],

    // Модели.
    'broadcast_model' => GeekCo\FilamentMaxBroadcasts\Models\Broadcast::class,
    'recipient_model' => GeekCo\FilamentMaxBroadcasts\Models\BroadcastRecipient::class,
    'segment_model'   => GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment::class,
    'chats_model'     => GeekCo\LaravelMaxClient\Models\MaxChat::class,
    'user_model'      => env('FILAMENT_MAX_BROADCASTS_USER_MODEL', \Illuminate\Foundation\Auth\User::class),

    // Типы рассылок: токен => класс, реализующий BroadcastTypeContract.
    // Каждый тип — свой backed-enum (case value === токен), поведение — в самом
    // типе: подписи (default из lang broadcasts.type.<token>), кнопки, цвет badge.
    // Простейший тип: подключить трейт BroadcastTypeDefaults и зарегистрировать ниже.
    'types' => [
        'news'  => GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\News::class,
        'promo' => GeekCo\FilamentMaxBroadcasts\Enums\BroadcastTypes\Promo::class,
    ],

    // Кнопки-диплинки в мини-приложение (данные по умолчанию для типов).
    // Трейт BroadcastTypeDefaults строит кнопки из buttons.per_type.<token>:
    // https://max.ru/<bot>?startapp=<param>. Кнопок для типа НЕТ, если bot_username
    // пуст, список для типа пуст или тип переопределил buttonRows().
    // Хост может переопределить кнопки целиком — реализовав buttonRows() в своём типе.
    // Пример для 'promo':
    // 'per_type' => [
    //     'promo' => [
    //         ['text' => 'Запись на сервис', 'startapp' => 'booking'],
    //         ['text' => 'Консультация',     'startapp' => 'consult'],
    //     ],
    // ],
    'bot_username' => env('FILAMENT_MAX_BROADCASTS_BOT_USERNAME', ''),
    'buttons' => [
        'per_type' => [],
    ],

    // Очередь / отправка.
    'queue' => [
        'batch_size'         => 25,
        'lock_ttl_seconds'   => 600,
        'tries'              => 3,
        'timeout'            => 3600,
        'backoff'            => [60, 300],
    ],

    // Вложения рассылки (картинки, видео, файлы — по несколько на рассылку).
    'image' => [
        'disk'      => env('FILAMENT_MAX_BROADCASTS_IMAGE_DISK', 'public'),
        'directory' => env('FILAMENT_MAX_BROADCASTS_IMAGE_DIRECTORY', 'broadcasts'),
        'max_kb'    => (int) env('FILAMENT_MAX_BROADCASTS_IMAGE_MAX_KB', 51200), // лимит MAX: 50 МБ = 51200 КБ
        'accepted_mime_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff', 'image/bmp', 'image/heic',
            'video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska',
        ],
    ],

    // Получатели.
    'recipients' => [
        'resolver' => GeekCo\FilamentMaxBroadcasts\Services\BroadcastRecipientsResolver::class,
    ],

    // Согласие получателей на рассылку (opt-in через callback-кнопки).
    // Слушатель HandleConsentCallback обрабатывает MessageCallback и складывает
    // chat_id в сегмент «Новости и акции» (consent.segment_name). Прочие сегменты
    // согласие не затрагивает: кнопка и записи привязаны только к этому сегменту.
    // Payload кнопок: consent:<action>.
    'consent' => [
        'consent_model'       => GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent::class,
        'segment_name'        => 'Новости и акции',
        'payload_prefix'      => 'consent',
        'request_message'     => 'Согласны ли вы получать наши новости и акции?',
        'button_text_opt_in'  => 'Согласен',
        'button_text_opt_out' => 'Не согласен',
        'answer_notification_opt_in'  => 'Вы согласились на получение рассылок.',
        'answer_notification_opt_out' => 'Вы отказались от получения рассылок.',
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
