<?php

return [
    // <livewire:notification-center> chrome -- role-agnostic, shared by all
    // three portals (see app/Livewire/NotificationCenter.php).
    'center' => [
        'bell_label' => 'Notifications',
        'heading' => 'Notifications',
        'mark_all_read_button' => 'Mark all as read',
        'empty' => 'No notifications yet.',
    ],

    // One line per Notification class's 'type' key (see app/Notifications/*)
    // -- the notification centre reads the stored `data` array and renders
    // the matching line here, rather than freezing translated text into the
    // database at fire time. Params match each notification's own
    // toArray() keys exactly (NotificationCenter::notificationText()
    // pre-formats buyer_price with thousands separators before this runs).
    'types' => [
        'request_broadcast' => 'New inquiry: :part_name (:request_code).',
        'vendor_response_submitted' => ':vendor_company_name responded to :request_code.',
        'quote_presented' => 'A new quote is available for :request_code -- ¥:buyer_price.',
        'quote_selected' => ':buyer_company_name selected a quote for :request_code.',
        'buyer_registered' => ':buyer_company_name registered and is awaiting approval.',
        'buyer_approved' => 'Your account has been approved -- you can now submit requests.',
        'part_request_submitted' => ':buyer_company_name submitted a new request: :part_name.',
    ],
];
