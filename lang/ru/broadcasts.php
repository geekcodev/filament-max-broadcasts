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

    'consent' => [
        'action' => [
            'opt_in' => 'Согласен',
            'opt_out' => 'Не согласен',
        ],
        'resource' => [
            'label' => 'Согласие',
            'plural_label' => 'Согласия',
            'navigation_label' => 'Согласия',
        ],
    ],

    'recipient_status' => [
        'pending' => 'Ожидает',
        'sent' => 'Отправлено',
        'failed' => 'Ошибка',
    ],

    'chat_types' => [
        'dialog' => 'Диалог',
        'chat' => 'Группа',
        'channel' => 'Канал',
        'unknown' => 'Чат',
    ],

    'chat_selection' => [
        'select_all' => 'Выбрать всех',
    ],

    'resource' => [
        'label' => 'Рассылка',
        'plural_label' => 'Рассылки',
        'navigation_label' => 'Рассылки',
        'navigation_group' => 'Рассылки',
    ],

    'form' => [
        'message_section' => 'Сообщение',
        'message_section_description' => 'Текст и медиавложения рассылки.',
        'type' => 'Тип рассылки',
        'type_helper' => 'Кнопки-диплинки в мини-приложение добавляются для типов, у которых они настроены.',
        'text' => 'Текст сообщения',
        'text_helper' => 'MAX поддерживает: жирный, курсив, подчёркнутый, зачёркнутый, выделенный, заголовки, цитату, моноширинный текст и ссылки. Лимит сообщения — 4000 символов.',
        'images' => 'Изображения',
        'images_helper' => 'Несколько изображений — можно прикрепить несколько файлов сразу.',
        'videos' => 'Видео',
        'files' => 'Файлы',
        'scheduled_at' => 'Время отправки',
        'scheduled_at_helper' => 'Оставьте пустым — рассылка уйдёт сразу после создания.',
        'recipients_section' => 'Получатели',
        'recipients_section_description' => 'Выберите сегменты и/или группы и каналы. Нужно заполнить хотя бы одно из полей.',
        'segments' => 'Сегменты получателей',
        'segment_default' => 'Выберите сегменты',
        'segments_helper' => 'Показаны только сегменты с согласившимися получателями; в бейдже — их число. Выбор сегмента заполнит список получателей теми, кто дал согласие; его можно скорректировать вручную.',
        'segments_no_consent' => 'В выбранных сегментах нет получателей с согласием. Запросите согласие или уберите сегменты.',
        'recipients' => 'Получатели',
        'recipients_helper' => 'Группы и каналы для ручного выбора. Диалоги приходят только из выбранных сегментов — от согласившихся получателей.',
        'attachments_section' => 'Вложения',
        'attachments' => 'Прикреплённые файлы',
        'attachment_item' => 'Вложение',
        'attachment_types' => [
            'image' => 'Изображение',
            'video' => 'Видео',
            'audio' => 'Аудио',
            'file' => 'Файл',
        ],
        'stats_section' => 'Статистика',
        'stats_type' => 'Тип',
        'stats_status' => 'Статус',
        'stats_sent_at' => 'Отправлена',
        'stats_delivered' => 'Доставлено',
        'stats_failed' => 'Ошибки',
        'stats_recipients' => 'Группа получателей',
        'no_segment' => 'Получатели вручную',
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
        'repeat_description' => 'Будет создана новая рассылка с этим же текстом, вложениями и теми же получателями.',
        'repeat_submit' => 'Повторить',
        'request_consent' => 'Запросить согласие',
        'request_consent_heading' => 'Разослать запрос согласия?',
        'request_consent_description' => 'Запрос «Вы согласны получать рассылку «название сегмента»?» будет отправлен диалогам этого сегмента, которые ещё не ответили. Ответившие (согласием или отказом) запрос больше не получат.',
        'request_consent_submit' => 'Отправить',
        'delete' => 'Удалить',
        'send_now' => 'Отправить сейчас',
        'cancel' => 'Отменить',
        'create' => 'Создать',
    ],

    'segment' => [
        'resource' => [
            'label' => 'Сегмент',
            'plural_label' => 'Сегменты',
            'navigation_label' => 'Сегменты',
        ],
        'form' => [
            'name' => 'Название',
            'description' => 'Описание',
            'recipients' => 'Получатели',
            'recipients_helper' => 'В сегмент можно добавить только диалоги. Группы и каналы выбираются в получателях рассылки.',
            'dialogs_only_error' => 'В сегмент можно добавить только диалоги.',
        ],
        'table' => [
            'id' => 'ID',
            'name' => 'Название',
            'recipients' => 'Получателей',
            'description' => 'Описание',
            'no_description' => '—',
            'created_at' => 'Создан',
        ],
        'view' => [
            'info_section' => 'Сегмент',
            'recipients_section' => 'Получатели',
            'recipients_section_description' => 'Чаты, добавленные в сегмент; рассылка уходит согласившимся диалогам.',
            'no_recipients' => 'Получателей нет',
            'chat_fallback' => 'Чат :id',
            'non_dialog_warning' => 'В сегменте есть группы или каналы: :count. Рассылка по сегменту их не получит.',
        ],
    ],

    'consent_table' => [
        'id' => 'ID',
        'name' => 'Имя чата',
        'anonymous_chat' => 'Чат :id',
        'chat_id' => 'ID чата',
        'user_id' => 'ID пользователя',
        'segment' => 'Сегмент',
        'action' => 'Действие',
        'source' => 'Источник',
        'created_at' => 'Дата',
        'filter_action' => 'Действие',
        'filter_segment' => 'Сегмент',
        'no_segment' => 'Без сегмента',
    ],

    'notifications' => [
        'broadcast_started' => 'Рассылка запущена',
        'broadcast_cancelled' => 'Рассылка отменена',
        'consent_request_started' => 'Запрос согласия отправлен :count получателям',
        'consent_no_recipients' => 'Нет диалогов для запроса согласия — все уже ответили',
        'repeat_empty' => 'Нечего повторять: у рассылки нет получателей',
    ],

    'recipients' => [
        'title' => 'Получатели',
        'name' => 'Имя пользователя',
        'user_id' => 'ID пользователя',
        'chat_id' => 'ID чата',
        'status' => 'Статус',
        'error' => 'Ошибка',
        'sent_at' => 'Отправлено',
        'filter_status' => 'Статус',
        'anonymous' => 'Пользователь :id',
    ],
];
