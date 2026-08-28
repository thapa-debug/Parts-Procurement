<?php

namespace App\Livewire\Admin;

use App\Enums\RequestStatus;
use App\Models\PartRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class RequestBoard extends Component
{
    use WithPagination;

    public string $search = '';

    /**
     * @var 'new'|'in_progress'|'purchased'|'completed'|'all'
     */
    public string $tab = 'all';

    /**
     * Groups the full status enum into the same broad stages the client's
     * prototype board uses. Only `new` is reachable yet -- the vendor
     * broadcast/quote/payment workflow that populates the rest is the
     * held-back next slice -- but the board is built against the full
     * lifecycle from the start rather than re-worked later.
     *
     * @return array<string, list<RequestStatus>>
     */
    public static function tabStatuses(): array
    {
        return [
            'new' => [RequestStatus::New],
            'in_progress' => [RequestStatus::VendorInquiry, RequestStatus::Quoted, RequestStatus::ProcurementFailed],
            'purchased' => [RequestStatus::Paid, RequestStatus::OrderedToVendor],
            'completed' => [RequestStatus::Shipped, RequestStatus::Received],
        ];
    }

    public function mount(): void
    {
        $this->authorize('viewBoard', PartRequest::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, PartRequest>
     */
    protected function requests(): LengthAwarePaginator
    {
        $statuses = static::tabStatuses()[$this->tab] ?? null;

        return PartRequest::query()
            ->with('buyer')
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('request_code', 'like', "%{$this->search}%")
                        ->orWhere('car_model', 'like', "%{$this->search}%")
                        ->orWhere('part_name', 'like', "%{$this->search}%")
                        ->orWhereHas('buyer', function ($query) {
                            $query->where('company_name', 'like', "%{$this->search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15);
    }

    /**
     * @return array<string, int>
     */
    protected function tabCounts(): array
    {
        $counts = ['all' => PartRequest::query()->count()];

        foreach (static::tabStatuses() as $tab => $statuses) {
            $counts[$tab] = PartRequest::query()->whereIn('status', $statuses)->count();
        }

        return $counts;
    }

    public function render(): View
    {
        return view('livewire.admin.request-board', [
            'requests' => $this->requests(),
            'tabCounts' => $this->tabCounts(),
        ])->title(__('admin.request_board.title'));
    }
}
