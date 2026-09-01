<?php

declare(strict_types=1);

return [

    'status' => [
        'scheduled' => 'Scheduled',
        'running' => 'Running',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'failed' => 'Failed',
    ],

    'type' => [
        'news' => 'News',
        'promo' => 'Promo',
    ],

    'recipient_status' => [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'failed' => 'Failed',
    ],

    'resource' => [
        'label' => 'Broadcast',
        'plural_label' => 'Broadcasts',
        'navigation_label' => 'Broadcasts',
        'navigation_group' => 'Broadcasts',
    ],

    'form' => [
        'message_section' => 'Message',
        'message_section_description' => 'The broadcast is sent to all active chats of the bot.',
        'type' => 'Broadcast type',
        'type_helper' => '«Promo» adds deep-link buttons to the mini-app under the message.',
        'text' => 'Message text',
        'text_helper' => 'MAX supports: bold, italic, underline, strikethrough, highlighted, headings, quote, monospace text and links. API limit — 4000 characters.',
        'image' => 'Image',
        'scheduled_at' => 'Send time',
        'scheduled_at_helper' => 'Leave empty to send immediately after creation.',
        'image_section' => 'Image',
        'attached_image' => 'Attached image',
        'stats_section' => 'Statistics',
        'stats_type' => 'Type',
        'stats_status' => 'Status',
        'stats_sent_at' => 'Sent at',
        'stats_delivered' => 'Delivered',
        'stats_failed' => 'Failed',
    ],

    'table' => [
        'id' => 'ID',
        'text' => 'Text',
        'image' => 'Image',
        'has_image' => 'Yes',
        'no_image' => '—',
        'type' => 'Type',
        'status' => 'Status',
        'total_recipients' => 'Recipients',
        'delivered_count' => 'Delivered',
        'filter_status' => 'Status',
        'filter_type' => 'Type',
    ],

    'actions' => [
        'repeat' => 'Repeat',
        'repeat_heading' => 'Repeat broadcast',
        'repeat_description' => 'A new broadcast with the same text and image will be created for all active chats.',
        'repeat_submit' => 'Repeat',
        'delete' => 'Delete',
        'send_now' => 'Send now',
        'cancel' => 'Cancel',
        'create' => 'Create',
    ],

    'notifications' => [
        'broadcast_started' => 'Broadcast started',
        'broadcast_cancelled' => 'Broadcast cancelled',
    ],

    'recipients' => [
        'title' => 'Recipients',
        'name' => 'Name',
        'user_id' => 'MAX user_id',
        'chat_id' => 'MAX chat_id',
        'status' => 'Status',
        'error' => 'Error',
        'sent_at' => 'Sent at',
        'filter_status' => 'Status',
        'anonymous' => 'User :id',
    ],
];
