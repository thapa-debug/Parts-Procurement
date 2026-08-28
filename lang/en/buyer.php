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
        'car_model_label' => 'Car model',
        'vin_label' => 'VIN / chassis number',
        'vin_help' => 'Optional, but helps vendors confirm an exact fit.',
        'mfg_date_label' => 'Manufacture date',
        'oem_part_number_label' => 'OEM part number',
        'part_name_label' => 'Part name & details',
        'reference_url_label' => 'Reference URL',
        'reference_url_help' => 'A listing or reference page for the part, if you have one.',
        'memo_label' => 'Notes',

        'submit' => 'Submit request',

        'submitted' => 'Request :code submitted. Our team will review it and reach out with next steps.',
    ],

];
