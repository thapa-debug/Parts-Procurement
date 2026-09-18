<x-admin.profile-edit-form
    :title="$buyerProfile->company_name"
    :back-route="route('admin.buyers.index')"
    :back-label="__('admin.buyer_master.back_link')"
    :account-fields="$accountFields"
    :fields="$fields"
/>
