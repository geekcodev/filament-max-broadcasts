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

    'consent' => [
        'action' => [
            'pending' => 'Awaiting response',
            'opt_in' => 'Agree',
            'opt_out' => 'Disagree',
        ],
        'resource' => [
            'label' => 'Consent',
            'plural_label' => 'Consents',
            'navigation_label' => 'Consents',
        ],
    ],

    'recipient_status' => [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'failed' => 'Failed',
    ],

    'chat_types' => [
        'dialog' => 'Dialog',
        'chat' => 'Group',
        'channel' => 'Channel',
        'unknown' => 'Chat',
    ],

    'chat_selection' => [
        'select_all' => 'Select all',
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
        'type_helper' => 'Deep-link buttons to the mini-app are added for types that have them configured.',
        'text' => 'Message text',
        'text_helper' => 'MAX supports: bold, italic, underline, strikethrough, highlighted, headings, quote, monospace text and links. Message limit — 4000 characters.',
        'images' => 'Images',
        'images_helper' => 'Multiple images — attach several files at once.',
        'videos' => 'Videos',
        'files' => 'Files',
        'scheduled_at' => 'Send time',
        'scheduled_at_helper' => 'Leave empty to send immediately after creation.',
        'recipients_section' => 'Recipients',
        'recipients_section_description' => 'Pick segments and/or groups and channels. At least one of the fields is required.',
        'segments' => 'Recipient segments',
        'segment_default' => 'Pick segments',
        'segments_helper' => 'Only segments with consented recipients are shown; the badge shows their number. Picking a segment fills the recipient list with those who agreed; you can adjust it manually.',
        'segments_no_consent' => 'The selected segments have no recipients who consented. Request consent or remove the segments.',
        'recipients' => 'Recipients',
        'recipients_helper' => 'Groups and channels for manual selection. Dialogs come only from the selected segments — from recipients who consented.',
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
        'no_segment' => 'Recipients picked manually',
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
        'request_consent_description' => 'The request “Do you agree to receive the mailing “segment name”?” will be sent to this segment’s dialogs that have not replied yet. Those who replied (agree or decline) will not receive it again.',
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
            'recipients_helper' => 'Only dialogs can be added to a segment. Groups and channels are picked in the broadcast recipients.',
            'dialogs_only_error' => 'Only dialogs can be added to a segment.',
        ],
        'table' => [
            'id' => 'ID',
            'name' => 'Name',
            'recipients' => 'Recipients',
            'description' => 'Description',
            'no_description' => '—',
            'created_at' => 'Created',
        ],
        'view' => [
            'info_section' => 'Segment',
            'recipients_section' => 'Recipients',
            'recipients_section_description' => 'Chats added to the segment; broadcasts go to the consented dialogs.',
            'no_recipients' => 'No recipients',
            'chat_fallback' => 'Chat :id',
            'non_dialog_warning' => 'The segment contains groups or channels: :count. Segment broadcasts will not reach them.',
        ],
    ],

    'consent_table' => [
        'id' => 'ID',
        'name' => 'Chat name',
        'anonymous_chat' => 'Chat :id',
        'chat_id' => 'Chat ID',
        'user_id' => 'User ID',
        'segment' => 'Segment',
        'action' => 'Action',
        'source' => 'Source',
        'source_values' => [
            'callback' => 'Button',
            'request' => 'Request',
        ],
        'created_at' => 'Date',
        'filter_action' => 'Action',
        'filter_segment' => 'Segment',
        'no_segment' => 'No segment',
        'empty_heading' => 'No consents yet',
        'empty_description' => 'Send a consent request from a segment — requests and responses will appear here.',
    ],

    'notifications' => [
        'broadcast_started' => 'Broadcast started',
        'broadcast_cancelled' => 'Broadcast cancelled',
        'consent_request_started' => 'Consent request sent to :count recipients',
        'consent_no_recipients' => 'No dialogs for the consent request — everyone has already replied',
        'repeat_empty' => 'Nothing to repeat: this broadcast has no recipients',
    ],

    'recipients' => [
        'title' => 'Recipients',
        'name' => 'User name',
        'user_id' => 'User ID',
        'chat_id' => 'Chat ID',
        'status' => 'Status',
        'error' => 'Error',
        'sent_at' => 'Sent at',
        'filter_status' => 'Status',
        'anonymous' => 'User :id',
    ],
];
