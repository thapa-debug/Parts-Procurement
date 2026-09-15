<?php

return [
    'name' => 'Heiwa Parts',
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
        'my_addresses' => 'My Addresses',
        'inbox' => 'Inbox',
    ],

    // Themed 403/404 (resources/views/errors/*.blade.php + <x-layouts.error>)
    // -- a user landing here is either signed in but in the wrong portal
    // (403) or followed a stale/mistyped link (404); "back home" routes
    // them to their own portal, not a generic landing page.
    'errors' => [
        'back_home' => 'Back to home',
        '403_heading' => "You don't have access to this page",
        '403_message' => "Your account doesn't have permission to view this page.",
        '404_heading' => 'Page not found',
        '404_message' => "The page you're looking for doesn't exist or may have moved.",
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
