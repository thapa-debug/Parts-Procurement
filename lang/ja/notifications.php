<?php

// DRAFT -- pending client review. Japanese wording for vendor-facing email
// only; the client should confirm it reads naturally for Japanese vendors.
//
// Vendors receive email in Japanese (User::preferredLocale() returns 'ja'),
// independent of the app's UI locale, which stays English. Only the keys a
// vendor can ever receive live here -- anything missing falls back to
// lang/en (fallback_locale), and buyer/admin email never reads this file.
// The Laravel-owned mail chrome (greeting, sign-off, button-fallback note,
// verification email) is translated in lang/ja.json.
//
// Keys and :params mirror lang/en/notifications.php exactly, so each
// notification's toMail() needs no locale-specific code.

return [

    'mail' => [
        'no_reply_notice' => '本メールは送信専用のため、ご返信いただいてもお答えできません。ご回答はポータルにログインのうえお願いいたします。',

        'request_broadcast' => [
            'subject' => '【新規打診】:part_name のお見積もりのお願い',
            'line' => '下記部品について、お見積もりのご依頼（打診）が届いております。ポータルにて内容をご確認のうえ、ご回答をお願いいたします。部品名：:part_name（依頼番号：:request_code）',
            'action' => '依頼内容を確認する',
        ],

        'order_confirmed_to_vendor' => [
            'subject' => '【発注確定】:part_name（:request_code）のご発注のお知らせ',
            'line' => 'お見積もりいただいた :part_name（依頼番号：:request_code）につきまして、採用が決定いたしましたので、正式に発注させていただきます。',
            'instruction' => [
                'carry' => '下記の納品先まで、部品のお持ち込みをお願いいたします。',
                'deliver' => '下記の納品先まで、部品のご発送をお願いいたします。',
                'both' => '下記の納品先まで、部品のお持ち込みまたはご発送をお願いいたします。',
            ],
            'address_heading' => '【納品先】:label',
            'address' => '〒:postal_code :prefecture :city :address_line1',
            'contact' => '受取担当：:recipient_name　TEL：:phone',
            'phone_only' => 'TEL：:phone',
            'action' => '発注内容を確認する',
            // Order-specific closing instead of the inquiry-style
            // no_reply_notice ("please respond via the portal") -- there's
            // nothing for the vendor to respond to here.
            'closing' => '本メールは送信専用です。ご不明点はポータルよりお問い合わせください。',
        ],
    ],

];
