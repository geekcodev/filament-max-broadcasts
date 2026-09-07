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
        'consent' => 'Consent poll',
    ],

    'consent' => [
        'action' => [
            'opt_in' => 'Agree',
            'opt_out' => 'Disagree',
        ],
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
        'message_section_description' => 'Message text and media attachments.',
        'type' => 'Broadcast type',
        'type_helper' => 'Deep-link buttons to the mini-app are added for types that have them configured (config buttons.per_type).',
        'text' => 'Message text',
        'text_helper' => 'MAX supports: bold, italic, underline, strikethrough, highlighted, headings, quote, monospace text and links. API limit — 4000 characters.',
        'images' => 'Images',
        'images_helper' => 'Multiple images — attach several files at once.',
        'videos' => 'Videos',
        'files' => 'Files',
        'scheduled_at' => 'Send time',
        'scheduled_at_helper' => 'Leave empty to send immediately after creation.',
        'recipients_section' => 'Recipients',
        'recipients_section_description' => 'Pick one or more recipient segments or tick specific chats manually. Leave everything empty to send to all active chats.',
        'segments' => 'Recipient segments',
        'segment_default' => 'No segments (all active chats)',
        'segments_helper' => 'Selecting segments fills the recipient list with the union of their chats. You can adjust the list manually.',
        'recipients' => 'Recipients',
        'recipients_helper' => 'Tick the chats to send to. Empty means all active chats.',
        'attachments_section' => 'Attachments',
        'attachments' => 'Attached files',
        'attachment_item' => 'Attachment',
        'attachment_types' => [
            'image' => 'Image',
            'video' => 'Video',
            'audio' => 'Audio',
            'file' => 'File',
        ],
        'stats_section' => 'Statistics',
        'stats_type' => 'Type',
        'stats_status' => 'Status',
        'stats_sent_at' => 'Sent at',
        'stats_delivered' => 'Delivered',
        'stats_failed' => 'Failed',
        'stats_recipients' => 'Recipient group',
        'no_segment' => 'All active chats',
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
        'repeat_description' => 'A new broadcast with the same text, attachments and the same recipients will be created.',
        'repeat_submit' => 'Repeat',
        'request_consent' => 'Request consent',
        'request_consent_heading' => 'Send the consent request?',
        'request_consent_description' => 'The request "Do you agree to receive our news and promos?" will be sent to all active chats that have not replied yet. Those who already replied will not receive it again.',
        'request_consent_submit' => 'Send',
        'delete' => 'Delete',
        'send_now' => 'Send now',
        'cancel' => 'Cancel',
        'create' => 'Create',
    ],

    'segment' => [
        'resource' => [
            'label' => 'Segment',
            'plural_label' => 'Segments',
            'navigation_label' => 'Segments',
        ],
        'form' => [
            'name' => 'Name',
            'description' => 'Description',
            'recipients' => 'Recipients',
            'recipients_helper' => 'Tick the chats to include in the segment.',
        ],
        'table' => [
            'id' => 'ID',
            'name' => 'Name',
            'recipients' => 'Recipients',
            'description' => 'Description',
            'no_description' => '—',
            'created_at' => 'Created',
        ],
    ],

    'notifications' => [
        'broadcast_started' => 'Broadcast started',
        'broadcast_cancelled' => 'Broadcast cancelled',
        'consent_request_started' => 'Consent request sent to :count recipients',
        'consent_no_recipients' => 'No recipients for the consent request — everyone has already replied',
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
