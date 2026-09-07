<?php

return [
    'name' => 'Parts Procurement',
    'tagline' => 'Auto-parts procurement brokerage',
    'footer_rights' => 'All rights reserved.',
    'logout' => 'Log out',

    'nav' => [
        'requests' => 'Requests',
        'vendors' => 'Vendors',
        'buyers' => 'Buyers',
        'settings' => 'Settings',
        'new_request' => 'New Request',
        'my_requests' => 'My Requests',
        'inbox' => 'Inbox',
    ],

    // <x-photo-gallery> -- shared by the admin quote-comparison view and the
    // buyer quote view (see CONVENTIONS.md on genuinely shared components):
    // domain-agnostic copy, not admin.* or buyer.*.
    'photo_gallery' => [
        'no_photos' => 'No photos provided.',
        'alt' => 'Part photo',
        'prev' => 'Previous photo',
        'next' => 'Next photo',
        'close' => 'Close',
        'counter' => ':current / :total',
    ],
];
