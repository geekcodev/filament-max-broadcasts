<?php

declare(strict_types=1);

return [

    'status' => [
        'scheduled' => 'Запланирована',
        'running' => 'Выполняется',
        'completed' => 'Завершена',
        'cancelled' => 'Отменена',
        'failed' => 'Ошибка',
    ],

    'type' => [
        'news' => 'Новость',
        'promo' => 'Акция',
    ],

    'recipient_status' => [
        'pending' => 'Ожидает',
        'sent' => 'Отправлено',
        'failed' => 'Ошибка',
    ],

    'resource' => [
        'label' => 'Рассылка',
        'plural_label' => 'Рассылки',
        'navigation_label' => 'Рассылки',
        'navigation_group' => 'Рассылки',
    ],

    'form' => [
        'message_section' => 'Сообщение',
        'message_section_description' => 'Рассылка уходит всем активным чатам бота.',
        'type' => 'Тип рассылки',
        'type_helper' => '«Акция» — под сообщением добавятся кнопки перехода в мини-приложение.',
        'text' => 'Текст сообщения',
        'text_helper' => 'MAX поддерживает: жирный, курсив, подчёркнутый, зачёркнутый, выделенный, заголовки, цитату, моноширинный текст и ссылки. Лимит API — 4000 символов.',
        'image' => 'Фото',
        'scheduled_at' => 'Время отправки',
        'scheduled_at_helper' => 'Оставьте пустым — рассылка уйдёт сразу после создания.',
        'image_section' => 'Фото',
        'attached_image' => 'Прикреплённое фото',
        'stats_section' => 'Статистика',
        'stats_type' => 'Тип',
        'stats_status' => 'Статус',
        'stats_sent_at' => 'Отправлена',
        'stats_delivered' => 'Доставлено',
        'stats_failed' => 'Ошибки',
    ],

    'table' => [
        'id' => 'ID',
        'text' => 'Текст',
        'image' => 'Фото',
        'has_image' => 'Да',
        'no_image' => '—',
        'type' => 'Тип',
        'status' => 'Статус',
        'total_recipients' => 'Получателей',
        'delivered_count' => 'Доставлено',
        'filter_status' => 'Статус',
        'filter_type' => 'Тип',
    ],

    'actions' => [
        'repeat' => 'Повторить',
        'repeat_heading' => 'Повторить рассылку',
        'repeat_description' => 'Будет создана новая рассылка с этим же текстом и фото всем активным чатам.',
        'repeat_submit' => 'Повторить',
        'delete' => 'Удалить',
        'send_now' => 'Отправить сейчас',
        'cancel' => 'Отменить',
        'create' => 'Создать',
    ],

    'notifications' => [
        'broadcast_started' => 'Рассылка запущена',
        'broadcast_cancelled' => 'Рассылка отменена',
    ],

    'recipients' => [
        'title' => 'Получатели',
        'name' => 'Имя',
        'user_id' => 'MAX user_id',
        'chat_id' => 'MAX chat_id',
        'status' => 'Статус',
        'error' => 'Ошибка',
        'sent_at' => 'Отправлено',
        'filter_status' => 'Статус',
        'anonymous' => 'Пользователь :id',
    ],
];
