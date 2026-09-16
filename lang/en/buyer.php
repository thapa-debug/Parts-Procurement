<?php

return [

    'request_form' => [
        'title' => 'New Part Request',
        'heading' => 'Submit a part request',
        'subheading' => 'Tell us what you need and our team will reach out to vendors on your behalf.',

        'blocked' => [
            'unverified_heading' => 'Verify your email to continue',
            'unverified_body' => "You'll be able to submit a request as soon as your email is verified. Use the banner above to resend the verification link if you need a new one.",
            'unapproved_heading' => 'Awaiting admin approval',
            'unapproved_body' => "Your account is awaiting admin approval. You'll be able to submit requests as soon as it's approved -- no action is needed from you in the meantime.",
        ],

        'maker_label' => 'Maker',
        'maker_placeholder_option' => '-- Select a maker --',

        'car_model_label' => 'Car model',
        'car_model_placeholder' => 'Examples: Crown / Land Cruiser',

        'vin_label' => 'VIN / chassis number',
        'vin_placeholder' => 'Example: GRS184-0002255',
        'vin_help' => 'Helps vendors confirm an exact fit.',

        'oem_part_number_label' => 'OEM part number',
        'oem_part_number_placeholder' => 'Example: 81110-60M00',

        'part_name_label' => 'Part name & details',
        'part_name_placeholder' => 'Example: Right LED headlight',

        'reference_url_label' => 'Reference URL',
        'reference_url_placeholder' => 'https://page.auctions.yahoo.co.jp/...',
        'reference_url_help' => 'A listing or reference page for the part, if you have one.',

        'memo_label' => 'Notes',
        'memo_placeholder' => 'e.g. specifications or your desired delivery date',

        'submit' => 'Submit request',

        'submitted' => 'Request :code submitted. Our team will review it and reach out with next steps.',
    ],

    'request_list' => [
        'title' => 'My Requests',
        'heading' => 'My requests',
        'subheading' => "Every part request you've submitted, and its current status.",
        'empty' => "You haven't submitted any requests yet.",
        'view_link' => 'View',

        'table' => [
            'code' => 'Request',
            'details' => 'Car / Part',
            'status' => 'Status',
            'requested_at' => 'Requested',
        ],

        'status' => [
            'new' => 'Submitted',
            'vendor_inquiry' => 'Checking availability',
            'quoted' => 'Quote ready',
            'paid' => 'Processing',
            'ordered_to_vendor' => 'Ordered',
            'procurement_failed' => 'Re-quoting',
            'shipped' => 'Shipped',
            'received' => 'Received',
        ],
    ],

    'request_detail' => [
        'back_link' => 'Back to my requests',

        'details_section' => 'Request details',
        'part_type_label' => 'Part type',
        'part_type' => [
            'used' => 'Used',
            'new' => 'New',
            'both' => 'Either',
        ],
        'maker_label' => 'Maker',
        'car_model_label' => 'Car model',
        'vin_label' => 'VIN / chassis number',
        'oem_part_number_label' => 'OEM part number',
        'part_name_label' => 'Part name & details',
        'reference_url_label' => 'Reference URL',
        'memo_label' => 'Notes',
        'requested_at_label' => 'Requested',
        'not_provided' => '—',

        'quote_section' => 'Your quote',
        'quote_options_help' => "Compare the options below and select the one you'd like to purchase. You can change your selection any time before you pay.",
        'quote_locked_help' => 'You\'ve paid for the option below -- it can no longer be changed.',
        'quote_price_label' => 'Price',
        'quote_price_excludes_shipping' => 'Price excludes shipping -- shipping is calculated at checkout based on your delivery address.',
        'quote_quality_label' => 'Quality rank',
        'quote_selected_badge' => 'Selected',
        'select_quote_button' => 'Select this quote',
        'select_quote_confirm' => 'Select this quote (¥:price)? You can change your selection any time before you pay.',
        'select_quote_error' => 'Something went wrong selecting this quote. Please refresh the page and try again.',
        'awaiting_quote' => 'No quotes are currently available for this request. Our team is sourcing options and will notify you when they\'re ready.',
        'checkout_button' => 'Proceed to checkout',

        'paid_banner_heading' => 'Payment confirmed',
        'paid_banner_body' => 'Your order is being processed -- we\'ll notify you when it ships.',

        'payment_summary_section' => 'Payment summary',
        'payment_summary_part_price' => 'Part price',
        'payment_summary_shipping_fee' => 'Shipping fee',
        'payment_summary_shipping_method' => 'Shipping method',
        'payment_summary_total' => 'Total paid',
        'payment_summary_shipping_to' => 'Shipping to',
    ],

    'address_book' => [
        'title' => 'My Addresses',
        'heading' => 'My addresses',
        'subheading' => 'Save delivery addresses to choose from at checkout.',
        'add_button' => 'Add address',
        'add_first_button' => 'Add your first address',
        'add_heading' => 'Add an address',
        'edit_heading' => 'Edit address',
        'empty' => "You haven't saved any addresses yet.",

        'recipient_name_label' => 'Recipient name',
        'phone_label' => 'Phone',
        'postal_code_label' => 'Postal code',
        'country_label' => 'Country',
        'country_placeholder_option' => '-- Select a country --',
        'state_label' => 'State / Province',
        'city_label' => 'City',
        'address_line1_label' => 'Address line 1',
        'address_line2_label' => 'Address line 2',
        'is_default_label' => 'Set as my default address',

        'save_button' => 'Save address',
        'cancel_button' => 'Cancel',
        'edit_button' => 'Edit',
        'delete_button' => 'Delete',
        'delete_confirm' => 'Delete this address? This cannot be undone.',
        'set_default_button' => 'Set as default',
        'default_badge' => 'Default',

        'created' => 'Address saved.',
        'updated' => 'Address updated.',
        'deleted' => 'Address deleted.',
        'default_updated' => 'Default address updated.',
    ],

    'checkout' => [
        'title' => 'Checkout -- :code',
        'heading' => 'Checkout -- :code',
        'back_link' => 'Back to request',
        'not_eligible' => 'This request is not ready for checkout -- make sure you have selected one of your presented quotes first.',

        'address_section' => 'Shipping address',
        'no_addresses' => "You haven't saved any addresses yet. Add one below to continue.",
        'add_new_address_button' => '+ Add a new address',
        'cancel_new_address_button' => 'Cancel new address',
        'save_new_address_button' => 'Save address',
        'address_added' => 'Address saved.',

        'method_section' => 'Shipping method',
        'method' => [
            'vehicle' => 'Vehicle shipment',
            'container' => 'Container shipment',
        ],
        'dhl_note' => 'DHL / express shipping is not yet available for online checkout -- contact us if you need this option.',

        'summary_section' => 'Order summary',
        'summary_part_price' => 'Part price',
        'summary_shipping_fee' => 'Shipping fee',
        'summary_total' => 'Total',

        'pay_button' => 'Pay now',
        'paid' => 'Your payment for :code is confirmed. Your order is being processed -- we\'ll notify you when it ships.',
        'error_not_allowed' => 'This request cannot be checked out right now. Please refresh the page and try again.',
        'error_payment_failed' => 'Your payment could not be processed. Please try again or use a different payment method.',
        'error_generic' => 'Something went wrong during checkout. Please try again.',
    ],

];
