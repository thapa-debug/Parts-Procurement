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
            // Whether the order was confirmed to THIS vendor -- computed as a
            // boolean in SQL so confirmed_vendor_id itself (which, for a
            // vendor who wasn't chosen, is another vendor's id) never leaves
            // the database.
            ->selectRaw('confirmed_vendor_id = ? as confirmed_to_me', [$vendorProfile->id])
            ->withCasts(['confirmed_to_me' => 'boolean'])
            ->orderByDesc('created_at')
            ->get();

        $confirmed = $invited->filter(fn (PartRequest $request) => (bool) $request->getAttribute('confirmed_to_me'));

        return view('livewire.vendor.inbox', [
            'confirmed' => $confirmed->values(),
            'pending' => $invited->where('responded', false)->values(),
            'responded' => $invited->where('responded', true)->diff($confirmed)->values(),
        ])->title(__('vendor.inbox.title'));
    }
}
