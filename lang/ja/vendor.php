<?php

// DRAFT -- pending client review. Japanese wording for the vendor portal's
// confirmed-order indicators only.
//
// The vendor portal currently renders in the app locale (English, CLAUDE.md
// §2), so these are not shown yet -- User::preferredLocale() switches
// notifications/mail only, not web pages. They're kept here, with the same
// keys and :params as lang/en/vendor.php, so turning on a Japanese vendor
// portal later needs no new copywriting for these screens. Anything missing
// falls back to English.

return [

    'inbox' => [
        'confirmed_section' => '発注確定済み（ご対応をお願いします）',
        'confirmed_help' => '以下のご注文は、お見積もりが採用され発注が確定しています。部品の納品と、請求書のご送付をお願いいたします。',
        'confirmed_badge' => '発注確定',
        'confirmed_hint' => '部品を納品のうえ、請求書をお送りください。',
    ],

    'request_response' => [
        'confirmed_badge' => '発注確定',
        'confirmed_heading' => 'お見積もりが採用され、発注が確定しました',
        'confirmed_help' => '下記のとおり、部品の納品手配をお願いいたします。',
        'delivery_section' => '納品先',
        'next_steps_heading' => '今後のお手続き',
        'next_step_deliver' => [
            'carry' => '納品先まで部品をお持ち込みください。',
            'deliver' => '納品先まで部品をご発送ください。',
            'both' => '納品先まで部品をお持ち込み、またはご発送ください。',
        ],
        'next_step_invoice' => '本件の請求書をお送りください（お見積もり金額：¥:price）。',
        'next_step_reference' => '納品書・請求書には依頼番号「:request_code」をご記載ください。',
    ],

];
