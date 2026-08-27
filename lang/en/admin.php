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

];
