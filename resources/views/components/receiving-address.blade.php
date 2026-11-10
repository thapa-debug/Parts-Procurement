{{-- The company receiving address snapshotted onto a part request at
order confirmation (ConfirmOrderToVendorAction) -- the receiving_* columns,
never the live CompanyAddress, so a later edit in Settings can't change it.
Shared by the admin request detail and the confirmed vendor's view; holds
nothing about the buyer. --}}
@props(['partRequest'])

<span class="block font-semibold">{{ $partRequest->receiving_label }}</span>
<span class="block">〒{{ $partRequest->receiving_postal_code }} {{ $partRequest->receiving_prefecture }} {{ $partRequest->receiving_city }}</span>
<span class="block">{{ $partRequest->receiving_address_line1 }}</span>
@if ($partRequest->receiving_address_line2)
    <span class="block">{{ $partRequest->receiving_address_line2 }}</span>
@endif
<span class="block">
    @if ($partRequest->receiving_recipient_name){{ $partRequest->receiving_recipient_name }} · @endif{{ $partRequest->receiving_phone }}
</span>
