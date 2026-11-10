<?php

// The vendor portal renders in the app locale (English) today, but every
// confirmed-order string a vendor sees has a Japanese draft ready in
// lang/ja/vendor.php -- a missing key would silently fall back to English
// (fallback_locale), so check each one really is translated.

it('has a Japanese draft for every confirmed-order string a vendor sees', function (string $key) {
    expect(__($key, [], 'ja'))->not->toBe(__($key, [], 'en'));
})->with([
    'vendor.inbox.confirmed_section',
    'vendor.inbox.confirmed_help',
    'vendor.inbox.confirmed_badge',
    'vendor.inbox.confirmed_hint',
    'vendor.request_response.confirmed_badge',
    'vendor.request_response.confirmed_heading',
    'vendor.request_response.confirmed_help',
    'vendor.request_response.delivery_section',
    'vendor.request_response.next_steps_heading',
    'vendor.request_response.next_step_deliver.carry',
    'vendor.request_response.next_step_deliver.deliver',
    'vendor.request_response.next_step_deliver.both',
    'vendor.request_response.next_step_invoice',
    'vendor.request_response.next_step_reference',
]);
