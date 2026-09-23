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
        'payment_confirmed' => 'Your payment for :request_code is confirmed -- your order is being processed.',
        'payment_confirmed_free' => 'Your free (無償) order for :request_code is confirmed -- your order is being processed.',
        'payment_confirmed_admin' => ':buyer_company_name paid for request :request_code -- ready to confirm the order to the vendor.',
        'payment_confirmed_admin_free' => ':buyer_company_name\'s free (無償) order for :request_code is confirmed -- ready to confirm the order to the vendor.',
    ],

    // Phase 3 Slice 2: email copy for each Notification class's toMail().
    // One subject/line/action per event, in the same shape/order every
    // time -- deliberately no reply-to-email framing (no_reply_notice
    // below is appended to every mail body instead), since every action
    // this app supports happens inside the portal, not over email.
    'mail' => [
        'no_reply_notice' => 'This is an automated notification -- please log in to the portal to respond. Replies to this email are not monitored.',

        'request_broadcast' => [
            'subject' => 'New inquiry: :part_name',
            'line' => 'A new part request needs your quote: :part_name (:request_code).',
            'action' => 'View request',
        ],

        'vendor_response_submitted' => [
            'subject' => ':vendor_company_name responded to :request_code',
            'line' => ':vendor_company_name submitted a quote for request :request_code.',
            'action' => 'View request',
        ],

        'quote_presented' => [
            'subject' => 'A new quote is ready for :request_code',
            'line' => 'A new quote is now available for your request :request_code -- ¥:buyer_price.',
            'action' => 'View your quote',
        ],

        'quote_selected' => [
            'subject' => ':buyer_company_name selected a quote',
            'line' => ':buyer_company_name selected a quote for request :request_code -- ¥:buyer_price.',
            'action' => 'View request',
        ],

        'buyer_registered' => [
            'subject' => 'New buyer registration: :buyer_company_name',
            'line' => ':buyer_company_name registered and is awaiting approval.',
            'action' => 'Review buyer',
        ],

        'buyer_approved' => [
            'subject' => 'Your account has been approved',
            'line' => 'Your account has been approved -- you can now submit part requests.',
            'action' => 'Go to my requests',
        ],

        'part_request_submitted' => [
            'subject' => 'New request: :part_name',
            'line' => ':buyer_company_name submitted a new request: :part_name (:request_code).',
            'action' => 'View request',
        ],

        'payment_confirmed' => [
            'subject' => 'Your payment for :request_code is confirmed',
            'line' => 'Your payment for :request_code is confirmed (¥:amount) -- your order is being processed.',
            'action' => 'View your order',
        ],

        'payment_confirmed_free' => [
            'subject' => 'Your free (無償) order for :request_code is confirmed',
            'line' => 'Your free (無償) order for :request_code is confirmed -- no payment was required. Your order is being processed.',
            'action' => 'View your order',
        ],

        'payment_confirmed_admin' => [
            'subject' => ':buyer_company_name paid for :request_code',
            'line' => ':buyer_company_name paid ¥:amount for request :request_code -- the order is now ready to confirm to the vendor.',
            'action' => 'View request',
        ],

        'payment_confirmed_admin_free' => [
            'subject' => ':buyer_company_name\'s free order is confirmed',
            'line' => ':buyer_company_name\'s free (無償) order for request :request_code is confirmed -- ready to confirm to the vendor.',
            'action' => 'View request',
        ],
    ],
];
