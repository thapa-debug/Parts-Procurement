{{-- The buyer's "Paid" confirmation on their chosen quote. Shown only once
the payment is genuinely confirmed (PartRequest::hasConfirmedPayment()) --
never at awaiting_payment. Green like the admin's paid indicator; a 無償
free order gets the cyan "Confirmed -- no payment needed" variant, also
matching the admin side. --}}
@props(['free' => false])

<span data-quote-badge="paid" {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium',
    'bg-cyan-50 text-cyan-700' => $free,
    'bg-green-50 text-green-700' => ! $free,
]) }}>
    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
    {{ $free ? __('buyer.request_detail.quote_free_confirmed_badge') : __('buyer.request_detail.quote_paid_badge') }}
</span>
