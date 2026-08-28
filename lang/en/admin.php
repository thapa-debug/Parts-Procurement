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

        'back_link' => 'Back to vendors',
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
            'approve_immediately_label' => 'Approve immediately',
            'approve_immediately_help' => 'Checked: this buyer can act as soon as they verify their email. Unchecked: they land in the pending-approval queue instead.',
            'submit' => 'Create buyer',
            'cancel' => 'Cancel',
        ],

        'reveal' => [
            'created_heading' => 'Buyer created',
            'reset_heading' => 'Password reset',
            'warning' => 'Save this now -- it will not be shown again. Relay it to the buyer yourself; the system never emails it.',
        ],

        'back_link' => 'Back to buyers',
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
        'verification_sent' => 'A verification email has been sent to :email. They\'ll need to verify before they can act.',
    ],

    // Shared by the vendor/buyer detail-edit view (<x-admin.profile-edit-form>):
    // the account block's labels are domain-agnostic (name/email mean the
    // same thing for a vendor or a buyer), unlike the editable fields below
    // it, which each caller supplies its own labels/fields for.
    'profile_edit' => [
        'edit_link' => 'Edit',
        'account_section' => 'Account',
        'name_label' => 'Name',
        'email_label' => 'Login email',
        'save_button' => 'Save changes',
        'saved' => 'Saved.',
    ],

    // Shared by the vendor/buyer master lists: the "act" gate (CLAUDE.md 14)
    // is role-agnostic, so both a vendor and a buyer can be unverified and
    // need the exact same badge/action -- neither the badge nor the resend
    // button is vendor- or buyer-specific copy.
    'verification' => [
        'column' => 'Verification',
        'verified_badge' => 'Verified',
        'unverified_badge' => 'Unverified',
        'resend_button' => 'Resend verification email',
        'resent' => 'Sent.',
    ],

    // <x-admin.row-actions-menu>'s trigger button text -- shared, since the
    // menu itself is domain-agnostic (see CONVENTIONS.md).
    'row_actions' => [
        'trigger' => 'Actions',
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

    'request_board' => [
        'title' => 'Requests',
        'subheading' => 'Every part request submitted by a buyer, across every stage of the pipeline.',
        'search_placeholder' => 'Search by request code, buyer, car model, or part...',
        'empty' => 'No requests here yet.',
        'empty_all' => 'No requests yet. Once a buyer submits one, it will appear here.',

        'tabs' => [
            'new' => 'New',
            'in_progress' => 'In progress',
            'purchased' => 'Purchased',
            'completed' => 'Completed',
            'all' => 'All',
        ],

        'table' => [
            'code' => 'Request',
            'buyer' => 'Buyer',
            'part_type' => 'Type',
            'details' => 'Car / Part',
            'requested_at' => 'Requested',
            'status' => 'Status',
        ],

        'part_type' => [
            'used' => 'Used',
            'new' => 'New',
            'both' => 'Either',
        ],

        'status' => [
            'new' => 'New',
            'vendor_inquiry' => 'Vendor inquiry',
            'quoted' => 'Quoted',
            'paid' => 'Paid',
            'ordered_to_vendor' => 'Ordered to vendor',
            'procurement_failed' => 'Procurement failed',
            'shipped' => 'Shipped',
            'received' => 'Received',
        ],
    ],

];
