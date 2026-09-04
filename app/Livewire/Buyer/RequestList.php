<?php

namespace App\Livewire\Buyer;

use App\Models\PartRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RequestList extends Component
{
    public function mount(): void
    {
        $this->authorize('viewOwnRequests', PartRequest::class);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $requests = PartRequest::query()
            ->select(['id', 'request_code', 'maker_id', 'car_model', 'part_name', 'status', 'created_at'])
            ->with('maker')
            ->where('buyer_id', $user->buyerProfile?->id)
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.buyer.request-list', [
            'requests' => $requests,
        ])->title(__('buyer.request_list.title'));
    }
}
