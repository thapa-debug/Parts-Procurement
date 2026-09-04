<?php

namespace App\Livewire\Vendor;

use App\Models\PartRequest;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Inbox extends Component
{
    public function mount(): void
    {
        $this->authorize('viewVendorInbox', PartRequest::class);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        /** @var VendorProfile $vendorProfile */
        $vendorProfile = $user->vendorProfile;

        // Explicit select -- keeps buyer_id and every other admin-only
        // column (status, snapshotted price fields, etc.) out of this
        // vendor-facing list entirely, not just out of the rendered HTML.
        $invited = PartRequest::query()
            ->select(['id', 'request_code', 'part_type', 'maker_id', 'car_model', 'oem_part_number', 'part_name', 'created_at'])
            ->with('maker')
            ->whereHas('vendors', fn ($query) => $query->where('vendor_profiles.id', $vendorProfile->id))
            ->withExists(['vendorResponses as responded' => fn ($query) => $query->where('vendor_id', $vendorProfile->id)])
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.vendor.inbox', [
            'pending' => $invited->where('responded', false)->values(),
            'responded' => $invited->where('responded', true)->values(),
        ])->title(__('vendor.inbox.title'));
    }
}
