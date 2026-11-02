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
    'chat_selection' => [
        'select_all' => 'Select all',
    ],

        'dialog' => 'Dialog',
        'chat' => 'Group',
        'channel' => 'Channel',
        'unknown' => 'Chat',
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
        'recipients_section_description' => 'Pick segments or tick chats manually. At least one of the two fields is required.',
        'segments' => 'Recipient segments',
        'segment_default' => 'Pick segments',
        'segments_helper' => 'Only segments with recipients are shown; the badge shows the number of active dialogs. Picking segments fills the recipient list, which you can adjust.',
        'recipients' => 'Recipients',
        'recipients_helper' => 'Tick the chats to send to. Dialogs, groups and channels are all allowed.',
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
        'request_consent_description' => 'The request “Do you agree to receive the mailing “segment name”?” will be sent to all active dialogs that have not replied to this segment yet. Those who already replied to it will not receive it again.',
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
            'recipients_section_description' => 'Dialogs that receive broadcasts from this segment.',
            'no_recipients' => 'No recipients',
            'chat_fallback' => 'Chat :id',
            'non_dialog_warning' => 'The segment contains groups or channels: :count. Segment broadcasts will not reach them.',
        ],
    ],

    'consent_table' => [
        'id' => 'ID',
        'name' => 'Name',
        'anonymous_chat' => 'Chat :id',
        'chat_id' => 'MAX chat_id',
        'user_id' => 'MAX user_id',
        'segment' => 'Segment',
        'action' => 'Action',
        'source' => 'Source',
        'created_at' => 'Date',
        'filter_action' => 'Action',
        'filter_segment' => 'Segment',
        'no_segment' => 'No segment',
    ],

    'notifications' => [
        'broadcast_started' => 'Broadcast started',
        'broadcast_cancelled' => 'Broadcast cancelled',
        'consent_request_started' => 'Consent request sent to :count recipients',
        'consent_no_recipients' => 'No dialogs for the consent request — everyone has already replied',
        'repeat_empty' => 'This broadcast cannot be repeated: it had no recipients',
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
