<?php

return [

    'vendor_master' => [
        'title' => 'Vendors',
        'subheading' => 'Manage vendor accounts: create, suspend, resume, and reset temporary passwords.',
        'search_placeholder' => 'Search by company, contact, or email...',
        'create_button' => 'New vendor',
        'empty' => 'No vendors yet. Add one to get started.',
        'empty_search' => 'No vendors match your search.',

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
            'name_placeholder' => 'e.g. Jane Smith',
            'name_help' => 'The name shown on this vendor\'s login account.',
            'email_label' => 'Login email',
            'email_placeholder' => 'you@example.com',
            'company_name_label' => 'Company name',
            'company_name_placeholder' => 'e.g. Acme Dismantlers Co.',
            'contact_person_label' => 'Contact person',
            'contact_person_placeholder' => 'e.g. Jane Smith',
            'contact_person_help' => 'Who to reach at this vendor for day-to-day communication.',
            'phone_label' => 'Phone',
            'phone_placeholder' => 'e.g. 03-1234-5678',
            'notify_email_label' => 'Notification email',
            'notify_email_placeholder' => 'you@example.com',
            'notify_email_help' => 'Where new inquiry broadcasts are sent -- can differ from the login email above.',
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
        'empty' => 'No buyers yet. Add one to get started.',
        'empty_search' => 'No buyers match your search.',

        'table' => [
            'company' => 'Company',
            'member_code' => 'Member code',
            'contact' => 'Contact',
            'email' => 'Login email',
            'actions' => 'Actions',
        ],

        // Buyer-only (CLAUDE.md §14) -- vendors have no approval concept,
        // so this stays scoped here rather than in the shared admin.verification
        // namespace the way the Verification badge is.
        'approval' => [
            'column' => 'Approval',
            'approved_badge' => 'Approved',
            'pending_badge' => 'Pending',
            'approve_button' => 'Approve',
            'pending_only_label' => 'Pending approval only',
        ],

        'reset_password_button' => 'Reset password',
        'reset_password_confirm' => "Reset :company's temporary password? Their current password stops working immediately.",

        'create_form' => [
            'title' => 'New buyer',
            'name_label' => 'Contact name',
            'name_placeholder' => 'e.g. Jane Smith',
            'email_label' => 'Login email',
            'email_placeholder' => 'you@example.com',
            'company_name_label' => 'Company name',
            'company_name_placeholder' => 'e.g. Acme Imports Ltd',
            'phone_label' => 'Phone',
            'phone_placeholder' => 'e.g. +61 4 1234 5678',
            'country_label' => 'Default destination country',
            'country_placeholder_option' => '-- Select a country --',
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
        'heading' => 'Settings',
        'subheading' => 'Controls the live margin PricingService applies to every quote, the shipping weight brackets used to calculate shipping fees, the address the system sends mail from, and the reference data (countries, makers) offered on buyer-facing forms.',

        'nav' => [
            'general' => 'General',
            'countries' => 'Countries',
            'makers' => 'Makers',
            'shipping_brackets' => 'Shipping',
        ],

        'margin_section' => 'Margin',
        'margin_rate_label' => 'Margin rate (%)',
        'margin_rate_placeholder' => 'e.g. 20',
        'margin_min_fee_label' => 'Minimum margin fee (¥)',
        'margin_min_fee_placeholder' => 'e.g. 2000',
        'margin_help' => 'Applied margin is whichever is larger: the percentage of cost price, or the minimum fee floor.',

        'sender_section' => 'Notifications',
        'admin_sender_email_label' => 'Admin sender email',
        'admin_sender_email_placeholder' => 'no-reply@yourcompany.com',
        'admin_sender_email_help' => 'The "from" address used when the system emails buyers and vendors.',

        'save_button' => 'Save settings',
        'saved' => 'Saved.',

        'countries_section' => 'Countries',
        'countries_help' => 'Countries buyers can select as their default destination. Deactivate instead of deleting -- existing buyer records keep their country even after it stops appearing in the dropdown.',
        'country_name_label' => 'Country name',
        'country_name_placeholder' => 'e.g. Singapore',
        'add_country_button' => 'Add country',
        'country_added' => 'Country added.',
        'country_updated' => 'Country updated.',
        'country_search_placeholder' => 'Search countries...',
        'country_empty' => 'No countries yet. Add one above.',
        'country_empty_search' => 'No countries match your search.',
        'country_table' => [
            'name' => 'Name',
            'status' => 'Status',
            'actions' => 'Actions',
        ],
        'country_status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],
        'edit_country_button' => 'Edit',
        'save_country_button' => 'Save',
        'cancel_button' => 'Cancel',
        'activate_country_button' => 'Activate',
        'deactivate_country_button' => 'Deactivate',

        'makers_section' => 'Makers',
        'makers_help' => 'Makers buyers can select on a part request. Deactivate instead of deleting -- existing requests keep their maker even after it stops appearing in the dropdown.',
        'maker_name_label' => 'Maker name',
        'maker_name_placeholder' => 'e.g. Isuzu',
        'add_maker_button' => 'Add maker',
        'maker_added' => 'Maker added.',
        'maker_updated' => 'Maker updated.',
        'maker_search_placeholder' => 'Search makers...',
        'maker_empty' => 'No makers yet. Add one above.',
        'maker_empty_search' => 'No makers match your search.',
        'maker_table' => [
            'name' => 'Name',
            'status' => 'Status',
            'actions' => 'Actions',
        ],
        'maker_status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],
        'edit_maker_button' => 'Edit',
        'save_maker_button' => 'Save',
        'activate_maker_button' => 'Activate',
        'deactivate_maker_button' => 'Deactivate',

        'shipping_brackets_section' => 'Shipping weight brackets',
        'shipping_brackets_help' => 'The shipping fee for a request is calculated automatically from the weight the vendor reports, using these brackets, at the moment the admin presents the quote -- not at checkout. Leave "Up to (kg)" blank for the top bracket ("and above", for very heavy items) -- only one bracket may be left blank at a time.',
        'bracket_upper_kg_label' => 'Up to (kg)',
        'bracket_upper_kg_placeholder' => 'e.g. 20, blank = and above',
        'bracket_upper_kg_help' => 'Blank means "and above" -- the catch-all bracket for anything heavier than every other bracket.',
        'bracket_fee_label' => 'Fee (¥)',
        'bracket_fee_placeholder' => 'e.g. 8000',
        'add_bracket_button' => 'Add bracket',
        'bracket_catch_all_exists' => 'Only one bracket can be left blank ("and above") at a time -- edit or delete the existing one first.',
        'bracket_table' => [
            'range' => 'Weight range',
            'fee' => 'Fee',
            'actions' => 'Actions',
        ],
        'bracket_range_from_zero' => 'Up to :to kg',
        'bracket_range' => 'Over :from kg, up to :to kg',
        'bracket_range_and_above' => 'Over :from kg (and above)',
        'bracket_empty' => 'No shipping weight brackets configured yet -- add one above.',
        'edit_bracket_button' => 'Edit',
        'save_bracket_button' => 'Save',
        'delete_bracket_button' => 'Delete',
        'delete_bracket_confirm' => 'Delete this shipping bracket? This cannot be undone.',
    ],

    'request_board' => [
        'title' => 'Requests',
        'subheading' => 'Every part request submitted by a buyer, across every stage of the pipeline.',
        'search_placeholder' => 'Search by request code, buyer, car model, or part...',
        'empty' => 'No requests here yet.',
        'empty_all' => 'No requests yet. Once a buyer submits one, it will appear here.',

        'tabs' => [
            'new' => 'New request',
            'inquiring' => 'Inquiring suppliers',
            'quoted' => 'Quote provided',
            'order_confirmed' => 'Order confirmed / inspection',
            'shipped' => 'Shipped',
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
            'actions' => 'Actions',
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

        'view_link' => 'View',
    ],

    'request_detail' => [
        'back_link' => 'Back to requests',

        'details_section' => 'Request details',
        'buyer_label' => 'Buyer',
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

        'paid_banner_heading' => 'Payment confirmed',
        'paid_banner_body' => 'Paid :date. The vendor purchase can now be confirmed.',

        'payment_summary_section' => 'Payment summary',
        'payment_summary_amount' => 'Amount charged',
        'payment_summary_gateway' => 'Gateway',
        'payment_summary_shipping_method' => 'Shipping method',
        'payment_summary_paid_at' => 'Paid at',
        'payment_summary_shipping_to' => 'Shipping to',

        'broadcast_section' => 'Send to vendors (打診)',
        'broadcast_help' => 'Select which active vendors should receive this inquiry. Suspended vendors are never shown here and can\'t be selected.',
        'select_all_button' => 'Select all',
        'no_active_vendors' => 'There are no active vendors to send this to yet. Activate or add a vendor first.',
        'vendor_column' => 'Vendor',
        'contact_column' => 'Contact',
        'send_button' => 'Send inquiry',
        'select_at_least_one' => 'Select at least one vendor before sending.',
        'sent_confirmation' => 'Inquiry sent to :count vendor(s).',
        'broadcast_error' => 'Something went wrong sending this inquiry. Please refresh the page and try again.',

        'sent_section' => 'Sent to vendors',
        'sent_help' => 'This request has already been broadcast -- no response deadline, so vendors can quote whenever they\'re ready.',
        'invited_at_column' => 'Invited',

        'compare_section' => 'Vendor quotes',
        'compare_help' => 'Check the vendor replies you want to present as priced quotes, then click "Present to buyer" to confirm.',
        'compare_locked_help' => 'This request has already been paid for -- quotes can no longer be presented.',
        'no_vendor_responses' => 'No vendor responses yet -- check back once an invited vendor replies.',
        'no_stock_badge' => 'No stock',
        'present_checkbox_label' => 'Select to present',
        'presented_badge' => 'Presented',
        'buyer_selected_badge' => 'Buyer selected',
        'cost_price_column' => 'Cost price',
        'buyer_price_column' => 'Buyer price',
        'quality_rank_column' => 'Quality',
        'lead_time_column' => 'Lead time',
        'shipping_fee_column' => 'Shipping (calculated)',
        'shipping_override_label' => 'Override shipping fee (¥)',
        'shipping_override_reason_label' => 'Reason for override',
        'shipping_override_reason_placeholder' => 'Required if overriding -- e.g. oversized crate needed',
        'free_badge' => 'Free (無償)',
        'present_as_free_label' => 'Present as free (無償) -- buyer pays ¥0',
        'present_as_free_help' => 'The buyer sees ¥0 for both the part and shipping. The vendor cost is still recorded for your own accounting -- you\'re absorbing it. Applies to every quote checked above; a request cannot mix free and paid quotes.',
        'select_at_least_one_quote' => 'Check at least one quote to present before continuing.',
        'present_selected_button' => 'Present to buyer',
        'present_selected_confirm' => 'Present the selected quote(s) to the buyer?',
        'present_quote_error' => 'Something went wrong presenting these quotes. Please refresh the page and try again.',
        'presented_toast' => 'Presented :count quote(s) to buyer.',
    ],

];
