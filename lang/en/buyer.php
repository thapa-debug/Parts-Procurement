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

        'part_type_label' => 'Part type',
        'part_type' => [
            'used' => 'Used',
            'new' => 'New',
            'both' => 'Either -- show me both',
        ],

        'maker_label' => 'Maker',
        'maker_placeholder' => 'e.g. Toyota, Nissan, Honda',

        'car_model_label' => 'Car model',
        'car_model_placeholder' => 'Examples: Crown / Land Cruiser',

        'vin_label' => 'VIN / chassis number',
        'vin_placeholder' => 'Example: GRS184-0002255',
        'vin_help' => 'Optional, but helps vendors confirm an exact fit.',

        'mfg_date_label' => 'Manufacture date',
        'mfg_date_help' => "Optional -- leave blank if you don't know it.",

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

];
