<?php

return [

    'inbox' => [
        'title' => 'Inbox',
        'heading' => 'Your inquiries',
        'subheading' => 'Requests our team has sent you for a quote.',

        'pending_section' => 'Awaiting your response',
        'pending_empty' => 'Nothing waiting on you right now.',

        'responded_section' => 'Responded',
        'responded_empty' => "You haven't responded to anything yet.",

        'no_stock_badge' => 'No stock',
        'view_link' => 'View',

        'part_type' => [
            'used' => 'Used',
            'new' => 'New',
            'both' => 'Either',
        ],
    ],

    'request_response' => [
        'back_link' => 'Back to inbox',

        'details_section' => 'Request details',
        'part_type_label' => 'Part type',
        'maker_label' => 'Maker',
        'car_model_label' => 'Car model',
        'vin_label' => 'VIN / chassis number',
        'oem_part_number_label' => 'OEM part number',
        'part_name_label' => 'Part name & details',
        'reference_url_label' => 'Reference URL',
        'memo_label' => 'Notes',
        'requested_at_label' => 'Requested',
        'not_provided' => '—',

        'blocked' => [
            'unverified_heading' => 'Verify your email to continue',
            'unverified_body' => "You'll be able to respond to this inquiry as soon as your email is verified. Use the banner above to resend the verification link if you need a new one.",
        ],

        'response_section' => 'Your quote',
        'cost_price_label' => 'Wholesale cost price (¥, excl. tax)',
        'cost_price_placeholder' => 'e.g. 45000',
        'quality_rank_label' => 'Quality rank',
        'lead_time_label' => 'Lead time to ship',

        'weight_dimensions_section' => 'Weight & dimensions',
        'weight_dimensions_help' => "Used to calculate shipping cost later. Please measure the part as you'd ship it, while you still have it in hand.",
        'weight_label' => 'Weight',
        'weight_placeholder' => 'e.g. 3.5',
        'weight_unit' => 'kg',
        'dimensions_label' => 'Dimensions (L × W × H)',
        'dimensions_unit' => 'cm',
        'length_placeholder' => 'e.g. 40',
        'width_placeholder' => 'e.g. 25',
        'height_placeholder' => 'e.g. 15',
        'length_caption' => 'Length',
        'width_caption' => 'Width',
        'height_caption' => 'Height',

        'photos_label' => 'Part photos',
        'photos_counter' => ':count / :max',
        'photos_dropzone_label' => 'Drag photos here, or click to browse',
        'photos_dropzone_help' => 'JPG or PNG, different angles, any damage.',
        'photos_help' => 'Photos of the actual part -- different angles, any damage.',
        'uploading' => 'Uploading',
        'max_photos_error' => 'You can attach up to :max photos.',
        'remove_photo' => 'Remove photo',
        'comment_label' => 'Condition notes',
        'comment_placeholder' => 'e.g. no visible damage, mounting bracket intact.',
        'submit_button' => 'Send quote to admin',

        'no_stock_section' => "Don't have this part?",
        'no_stock_help' => 'Skip the form above and reply with one tap instead.',
        'no_stock_button' => 'No stock',
        'no_stock_confirm' => 'Report that you have no stock for this part?',

        'submit_error' => 'Something went wrong sending this response. Please refresh the page and try again.',

        'submitted_section' => 'Your response',
        'submitted_no_stock' => 'You reported no stock for this part.',
        'submitted_quote' => 'You quoted ¥:price, rank :rank, :lead_time.',
    ],

];
