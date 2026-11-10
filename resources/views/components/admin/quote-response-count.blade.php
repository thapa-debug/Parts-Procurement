{{-- "3 / 5 quotes received · 1 no stock" for a request at the
vendor_inquiry stage -- real quotes out of the vendors it was broadcast
to, with one-tap "no stock" replies noted separately. Shared by the
request board and the request detail header; the counts come from
PartRequest::responseCounts(). Highlighted once at least one real quote
is in, i.e. there's something to present. --}}
@props(['quotes', 'invited', 'noStock' => 0])

<span
    {{ $attributes->class([
        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium',
        'border-blue-200 bg-surface text-blue-700' => $quotes > 0,
        'border-line bg-surface text-ink-muted' => $quotes <= 0,
    ]) }}
    title="{{ __('admin.request_board.quotes_received_title', ['quotes' => $quotes, 'invited' => $invited, 'no_stock' => $noStock]) }}"
>
    {{ trans_choice('admin.request_board.quotes_received', $quotes, ['quotes' => $quotes, 'invited' => $invited]) }}
    @if ($noStock > 0)
        <span class="text-ink-muted">&middot; {{ __('admin.request_board.no_stock_replies', ['count' => $noStock]) }}</span>
    @endif
</span>
