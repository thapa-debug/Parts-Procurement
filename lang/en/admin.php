<?php

return [

    'vendor_master' => [
        'title' => 'Vendors',
        'subheading' => 'Manage vendor accounts: create, suspend, resume, and reset temporary passwords.',
        'search_placeholder' => 'Search by company, contact, or email...',
        'create_button' => 'New vendor',
        'empty' => 'No vendors found.',

        'table' => [
            'company' => 'Company',
            'contact' => 'Contact',
            'email' => 'Login email',
            'status' => 'Status',
            'actions' => 'Actions',
        ],

        'status' => [
            'active' => 'Active',
            'suspended' => 'Suspended',
        ],

        'suspend_button' => 'Suspend',
        'resume_button' => 'Resume',
        'reset_password_button' => 'Reset password',
        'suspend_confirm' => 'Suspend :company? They will no longer be sent new inquiries.',
        'reset_password_confirm' => "Reset :company's temporary password? Their current password stops working immediately.",

        'create_form' => [
            'title' => 'New vendor',
            'name_label' => 'Contact name',
            'email_label' => 'Login email',
            'company_name_label' => 'Company name',
            'contact_person_label' => 'Contact person',
            'phone_label' => 'Phone',
            'notify_email_label' => 'Notification email',
            'submit' => 'Create vendor',
            'cancel' => 'Cancel',
        ],

        'reveal' => [
            'created_heading' => 'Vendor created',
            'reset_heading' => 'Password reset',
            'warning' => 'Save this now -- it will not be shown again. Relay it to the vendor yourself; the system never emails it.',
        ],
    ],

    'buyer_master' => [
        'title' => 'Buyers',
        'subheading' => 'Manage buyer accounts: create and reset temporary passwords.',
        'search_placeholder' => 'Search by company, member code, name, or email...',
        'create_button' => 'New buyer',
        'empty' => 'No buyers found.',

        'table' => [
            'company' => 'Company',
            'member_code' => 'Member code',
            'contact' => 'Contact',
            'email' => 'Login email',
            'actions' => 'Actions',
        ],

        'reset_password_button' => 'Reset password',
        'reset_password_confirm' => "Reset :company's temporary password? Their current password stops working immediately.",

        'create_form' => [
            'title' => 'New buyer',
            'name_label' => 'Contact name',
            'email_label' => 'Login email',
            'company_name_label' => 'Company name',
            'phone_label' => 'Phone',
            'default_destination_country_label' => 'Default destination country',
            'default_yard_label' => 'Default yard',
            'submit' => 'Create buyer',
            'cancel' => 'Cancel',
        ],

        'reveal' => [
            'created_heading' => 'Buyer created',
            'reset_heading' => 'Password reset',
            'warning' => 'Save this now -- it will not be shown again. Relay it to the buyer yourself; the system never emails it.',
        ],
    ],

    // Shared across every admin-created-account reveal ceremony (vendor,
    // buyer, ...): heading/warning are context-specific and come from the
    // caller instead, since "Vendor created" would be wrong copy for a buyer.
    'reveal' => [
        'for' => 'Temporary password for :company',
        'copy_button' => 'Copy',
        'copied' => 'Copied',
        'acknowledge_label' => "I've saved this password",
        'dismiss_button' => 'Done',
    ],

    'settings' => [
        'title' => 'Settings',
        'heading' => 'Pricing & shipping settings',
        'subheading' => 'Controls the live margin PricingService applies to every quote, the shipping fees shown to buyers, and the address the system sends mail from.',

        'margin_section' => 'Margin',
        'margin_rate_label' => 'Margin rate (%)',
        'margin_min_fee_label' => 'Minimum margin fee (¥)',
        'margin_help' => 'Applied margin is whichever is larger: the percentage of cost price, or the minimum fee floor.',

        'shipping_section' => 'Shipping fees',
        'shipping_fee_vehicle_label' => 'Vehicle (¥)',
        'shipping_fee_container_label' => 'Container (¥)',
        'shipping_help' => 'Provisional fixed fees, pending client confirmation. DHL is not configured here -- it varies per request and is entered by the admin at quote time.',

        'sender_section' => 'Notifications',
        'admin_sender_email_label' => 'Admin sender email',
        'admin_sender_email_help' => 'The "from" address used when the system emails buyers and vendors.',

        'save_button' => 'Save settings',
        'saved' => 'Saved.',
    ],

];
