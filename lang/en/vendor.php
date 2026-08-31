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
        'quality_rank' => [
            's' => 'S -- Like new / OEM equivalent',
            'a' => 'A -- Excellent condition',
            'b' => 'B -- Minor wear, may need light repair',
            'c' => 'C -- Junk / parts only',
        ],
        'lead_time_label' => 'Lead time to ship',
        'lead_time' => [
            'within_2_days' => 'Same day to 2 days',
            'within_1_week' => '3 days to 1 week',
            'within_2_weeks' => 'Within 2 weeks',
            'undetermined' => 'Undetermined',
        ],
        'photos_label' => 'Part photo',
        'photos_help' => 'A photo of the actual part.',
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
