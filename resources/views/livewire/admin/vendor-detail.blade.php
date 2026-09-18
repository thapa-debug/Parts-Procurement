<x-admin.profile-edit-form
    :title="$vendorProfile->company_name"
    :back-route="route('admin.vendors.index')"
    :back-label="__('admin.vendor_master.back_link')"
    :account-fields="$accountFields"
    :fields="$fields"
/>
