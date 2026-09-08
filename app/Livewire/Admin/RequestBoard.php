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
     * @var 'new'|'inquiring'|'quoted'|'order_confirmed'|'shipped'|'completed'|'all'
     */
    public string $tab = 'all';

    /**
     * Mirrors CLAUDE.md §5's full lifecycle one stage per tab, not a
     * collapsed grouping -- only `new` is reachable yet (the vendor
     * broadcast/quote/payment workflow that populates the rest is the
     * held-back next slice), but the board is built against the full
     * lifecycle from the start rather than re-worked later.
     *
     * `order_confirmed` bundles `paid` + `ordered_to_vendor` +
     * `procurement_failed`: all three are "we've been paid, now arranging
     * fulfillment" from an admin's-eye view, and `procurement_failed` has
     * no JP label of its own in CLAUDE.md §5 (routes back to re-quoting
     * rather than being a distinct client-facing stage).
     *
     * @return array<string, list<RequestStatus>>
     */
    public static function tabStatuses(): array
    {
        return [
            'new' => [RequestStatus::New],
            'inquiring' => [RequestStatus::VendorInquiry],
            'quoted' => [RequestStatus::Quoted],
            'order_confirmed' => [RequestStatus::Paid, RequestStatus::OrderedToVendor, RequestStatus::ProcurementFailed],
            'shipped' => [RequestStatus::Shipped],
            'completed' => [RequestStatus::Received],
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

    /**
     * Tab color reads from the same single source as every status badge
     * (RequestStatus::badgeClasses(), UX brush-up) -- each tab uses its
     * group's first/representative status ('order_confirmed''s is Paid,
     * the happy path; a row that's actually procurement_failed still
     * shows red on its own badge regardless of which tab it's sitting in).
     * 'all' isn't a real status, so it stays neutral.
     *
     * @return array<string, string>
     */
    protected function tabBadgeClasses(): array
    {
        $classes = ['all' => 'bg-surface-muted text-ink-muted'];

        foreach (static::tabStatuses() as $tab => $statuses) {
            $classes[$tab] = $statuses[0]->badgeClasses();
        }

        return $classes;
    }

    public function render(): View
    {
        return view('livewire.admin.request-board', [
            'requests' => $this->requests(),
            'tabCounts' => $this->tabCounts(),
            'tabBadgeClasses' => $this->tabBadgeClasses(),
        ])->title(__('admin.request_board.title'));
    }
}
