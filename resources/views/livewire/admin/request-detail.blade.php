<div class="max-w-3xl">
    <a href="{{ route('admin.requests.index') }}" class="text-sm text-ink-muted hover:text-ink">
        &larr; {{ __('admin.request_detail.back_link') }}
    </a>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-ink">{{ $partRequest->request_code }}</h1>
        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $partRequest->status->badgeClasses() }}">
            {{ __('admin.request_board.status.'.$partRequest->status->value) }}
        </span>
    </div>

    {{-- shipping_method is only set once CheckoutAction has actually run --
    a confirmed payment alone isn't enough (e.g. a future free/無償 request
    may reach `paid` without ever going through checkout). --}}
    @if ($confirmedPayment && $partRequest->shipping_method)
        <x-paid-status-banner
            :heading="__('admin.request_detail.paid_banner_heading')"
            :body="__('admin.request_detail.paid_banner_body', ['date' => $confirmedPayment->paid_at->format('Y-m-d H:i')])"
        />

        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.payment_summary_section') }}</h2>

            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.payment_summary_amount') }}</dt>
                    <dd class="mt-0.5 font-mono text-lg font-semibold text-ink">¥{{ number_format($confirmedPayment->amount) }}</dd>
                </div>

                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.payment_summary_gateway') }}</dt>
                    <dd class="mt-0.5 font-medium text-ink">{{ $confirmedPayment->gateway }}</dd>
                </div>

                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.payment_summary_shipping_method') }}</dt>
                    <dd class="mt-0.5 font-medium text-ink">{{ __('enums.shipping_method.'.$partRequest->shipping_method->value) }} (¥{{ number_format($partRequest->shipping_fee) }})</dd>
                </div>

                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.payment_summary_paid_at') }}</dt>
                    <dd class="mt-0.5 font-medium text-ink">{{ $confirmedPayment->paid_at->format('Y-m-d H:i') }}</dd>
                </div>

                <div class="sm:col-span-2">
                    <dt class="text-ink-muted">{{ __('admin.request_detail.payment_summary_shipping_to') }}</dt>
                    <dd class="mt-0.5 font-medium text-ink">
                        {{ $partRequest->shipping_recipient_name }} ({{ $partRequest->shipping_phone }})<br>
                        {{ $partRequest->shipping_postal_code }}, {{ $partRequest->shipping_country }}@if ($partRequest->shipping_state), {{ $partRequest->shipping_state }}@endif, {{ $partRequest->shipping_city }}<br>
                        {{ $partRequest->shipping_address_line1 }}
                        @if ($partRequest->shipping_address_line2)
                            <br>{{ $partRequest->shipping_address_line2 }}
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    @endif

    <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.details_section') }}</h2>

        <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-ink-muted">{{ __('admin.request_detail.buyer_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->buyer->company_name }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('admin.request_detail.part_type_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ __('admin.request_board.part_type.'.$partRequest->part_type->value) }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('admin.request_detail.maker_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->maker->name }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('admin.request_detail.car_model_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->car_model }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('admin.request_detail.vin_label') }}</dt>
                <dd class="mt-0.5 font-mono font-medium text-ink">{{ $partRequest->vin }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('admin.request_detail.oem_part_number_label') }}</dt>
                <dd class="mt-0.5 font-mono font-medium text-ink">{{ $partRequest->oem_part_number ?? __('admin.request_detail.not_provided') }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-ink-muted">{{ __('admin.request_detail.part_name_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->part_name }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-ink-muted">{{ __('admin.request_detail.reference_url_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">
                    @if ($partRequest->reference_url)
                        <a href="{{ $partRequest->reference_url }}" target="_blank" rel="noopener noreferrer" class="break-all text-brand-700 hover:underline">
                            {{ $partRequest->reference_url }}
                        </a>
                    @else
                        {{ __('admin.request_detail.not_provided') }}
                    @endif
                </dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-ink-muted">{{ __('admin.request_detail.memo_label') }}</dt>
                <dd class="mt-0.5 whitespace-pre-line font-medium text-ink">{{ $partRequest->memo ?? __('admin.request_detail.not_provided') }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('admin.request_detail.requested_at_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->created_at->format('Y-m-d H:i') }}</dd>
            </div>
        </dl>
    </div>

    @if ($sentToCount !== null)
        <div class="mt-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700">
            {{ __('admin.request_detail.sent_confirmation', ['count' => $sentToCount]) }}
        </div>
    @endif

    @if ($partRequest->status === \App\Enums\RequestStatus::New)
        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.broadcast_section') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ __('admin.request_detail.broadcast_help') }}</p>

            @if ($activeVendors->isEmpty())
                <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    {{ __('admin.request_detail.no_active_vendors') }}
                </p>
            @else
                <div class="mt-4 flex items-center justify-between">
                    <button
                        type="button"
                        wire:click="selectAllVendors"
                        class="text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        {{ __('admin.request_detail.select_all_button') }}
                    </button>
                </div>

                <div class="mt-2 grid max-h-72 grid-cols-1 gap-2 overflow-y-auto rounded-md border border-line bg-surface-muted p-3 sm:grid-cols-2">
                    @foreach ($activeVendors as $vendor)
                        <label class="flex items-center gap-2 rounded-md border border-line bg-surface p-2.5 text-sm text-ink shadow-sm hover:border-brand-500">
                            <input
                                type="checkbox"
                                wire:model="selectedVendorIds"
                                value="{{ $vendor->id }}"
                                class="rounded border-line text-brand-600 focus:ring-1 focus:ring-brand-500"
                            >
                            <span>
                                <span class="block font-medium">{{ $vendor->company_name }}</span>
                                <span class="block text-xs text-ink-muted">{{ $vendor->contact_person }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('selectedVendorIds')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="mt-4">
                    <button
                        type="button"
                        wire:click="sendInquiry"
                        wire:loading.attr="disabled"
                        wire:target="sendInquiry"
                        class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ __('admin.request_detail.send_button') }}
                    </button>
                </div>
            @endif
        </div>
    @else
        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.sent_section') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ __('admin.request_detail.sent_help') }}</p>

            <div class="mt-4 overflow-x-auto rounded-md border border-line">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-muted">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-ink-muted">
                            <th class="px-4 py-2.5">{{ __('admin.request_detail.vendor_column') }}</th>
                            <th class="px-4 py-2.5">{{ __('admin.request_detail.contact_column') }}</th>
                            <th class="px-4 py-2.5">{{ __('admin.request_detail.invited_at_column') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($invitedVendors as $vendor)
                            <tr>
                                <td class="px-4 py-2.5 font-medium text-ink">{{ $vendor->company_name }}</td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ $vendor->contact_person }}</td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ $vendor->pivot->invited_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($partRequest->status !== \App\Enums\RequestStatus::New)
        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.compare_section') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">
                {{ $partRequest->hasBeenPaid()
                    ? __('admin.request_detail.compare_locked_help')
                    : __('admin.request_detail.compare_help') }}
            </p>

            @error('presentQuote')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if ($vendorResponses->isEmpty())
                <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    {{ __('admin.request_detail.no_vendor_responses') }}
                </p>
            @else
            <div class="mt-4 space-y-4">
                @foreach ($vendorResponses as $response)
                    @php
                        $pricing = $vendorResponsePricing->get($response->id);
                        $isPresented = in_array($response->id, $presentedResponseIds, true);
                        $isBuyerSelected = $partRequest->selected_response_id === $response->id;
                        $presentedQuote = $presentedQuotesByResponseId->get($response->id);
                        // The buyer's own pick has a confirmed payment once
                        // the pay-before-purchase gate (CLAUDE.md §6.3) is
                        // actually open -- $confirmedPayment itself is
                        // request-wide (there's only ever one), so it only
                        // means anything for whichever response the buyer
                        // selected; another presented-but-unchosen response
                        // never reads as paid just because the request is.
                        $isPaidAndSelected = $isBuyerSelected && $confirmedPayment !== null;
                    @endphp
                    @php
                        // Distinct visual states, most money-significant
                        // first: an open payment gate (real or 無償, CLAUDE.md
                        // §14 Phase 4 slice 5) is the strongest signal on this
                        // page -- it's what lets the admin confirm to the
                        // vendor -- so it always wins over the plain
                        // "selected" or "presented" treatment beneath it.
                        $rowClass = match (true) {
                            // Cyan, not emerald -- emerald is already the
                            // plain "Free (無償)" tag's own color (below), and
                            // sitting too close to the new green Paid
                            // treatment would blur the exact distinction
                            // this slice exists to make.
                            $isPaidAndSelected && $partRequest->is_free => 'border-cyan-300 bg-cyan-50',
                            $isPaidAndSelected => 'border-green-300 bg-green-50',
                            $isBuyerSelected => 'border-brand-500 bg-brand-50',
                            $isPresented => 'border-blue-200 bg-blue-50/60',
                            default => 'border-line',
                        };
                    @endphp
                    <div class="rounded-md border p-4 {{ $rowClass }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <span class="font-medium text-ink">{{ $response->vendor->company_name }}</span>
                                <span class="ml-2 text-xs text-ink-muted">{{ $response->vendor->contact_person }}</span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($response->is_no_stock)
                                    <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">
                                        {{ __('admin.request_detail.no_stock_badge') }}
                                    </span>
                                @endif

                                @if ($isPresented)
                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                        {{ __('admin.request_detail.presented_badge') }}
                                    </span>

                                    {{-- "Free (無償)" marks what the quote WOULD cost the
                                    buyer if chosen -- once it's actually confirmed, the
                                    dedicated free-confirmed badge below takes over so
                                    the two are never shown at once. --}}
                                    @if ($presentedQuote?->is_free && ! $isPaidAndSelected)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                            {{ __('admin.request_detail.free_badge') }}
                                        </span>
                                    @endif

                                    @if ($isBuyerSelected)
                                        <span class="inline-flex rounded-full bg-brand-100 px-2.5 py-1 text-xs font-medium text-brand-700">
                                            {{ __('admin.request_detail.buyer_selected_badge') }}
                                        </span>
                                    @endif

                                    @if ($isPaidAndSelected)
                                        @if ($partRequest->is_free)
                                            <span class="inline-flex rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-semibold text-cyan-800">
                                                {{ __('admin.request_detail.free_confirmed_badge') }}
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">
                                                {{ __('admin.request_detail.paid_badge') }}
                                            </span>
                                        @endif
                                    @endif
                                @endif
                            </div>
                        </div>

                        @if (! $response->is_no_stock)
                            <div class="mt-3 overflow-x-auto rounded-md border border-line">
                                <table class="min-w-full divide-y divide-line text-sm">
                                    <thead class="bg-surface-muted">
                                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-ink-muted">
                                            <th class="whitespace-nowrap px-3 py-2">{{ __('admin.request_detail.cost_price_column') }}</th>
                                            <th class="whitespace-nowrap px-3 py-2">{{ __('admin.request_detail.buyer_price_column') }}</th>
                                            <th class="px-3 py-2">{{ __('admin.request_detail.quality_rank_column') }}</th>
                                            <th class="px-3 py-2">{{ __('admin.request_detail.lead_time_column') }}</th>
                                            <th class="whitespace-nowrap px-3 py-2">{{ __('admin.request_detail.weight_column') }}</th>
                                            <th class="whitespace-nowrap px-3 py-2">{{ __('admin.request_detail.shipping_fee_column') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line bg-surface">
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2 font-mono font-medium text-ink">¥{{ number_format($response->cost_price) }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 font-mono font-medium text-ink">
                                                @if ($presentedQuote)
                                                    ¥{{ number_format($presentedQuote->buyer_price) }}
                                                @elseif ($pricing)
                                                    ¥{{ number_format($pricing['buyer_price']) }}
                                                @else
                                                    {{ __('admin.request_detail.not_provided') }}
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-ink">{{ __('enums.quality_rank.'.$response->quality_rank->value) }}</td>
                                            <td class="px-3 py-2 text-ink">{{ __('enums.lead_time.'.$response->lead_time->value) }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 font-mono text-ink">
                                                {{ $response->weight_kg !== null ? $response->weight_kg.' kg' : __('admin.request_detail.not_provided') }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 font-mono font-medium text-ink">
                                                @if ($presentedQuote)
                                                    ¥{{ number_format($presentedQuote->shipping_fee) }}
                                                @else
                                                    @php $calculatedShipping = $vendorResponseShipping->get($response->id); @endphp
                                                    {{ $calculatedShipping !== null ? '¥'.number_format($calculatedShipping) : __('admin.request_detail.not_provided') }}
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            @if ($response->comment)
                                <p class="mt-3 text-sm text-ink">{{ $response->comment }}</p>
                            @endif

                            <div class="mt-3 max-w-sm">
                                <x-photo-gallery :photos="$response->photos->map(fn ($photo) => $photo->url())->all()" />
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                @unless ($isPresented)
                                    <label class="flex items-center gap-2 text-sm font-medium text-ink {{ $partRequest->hasBeenPaid() ? 'opacity-50' : '' }}">
                                        <input
                                            type="checkbox"
                                            wire:model="selectedResponseIdsToPresent"
                                            value="{{ $response->id }}"
                                            {{ $partRequest->hasBeenPaid() ? 'disabled' : '' }}
                                            class="rounded border-line text-brand-600 focus:ring-1 focus:ring-brand-500"
                                        >
                                        {{ __('admin.request_detail.present_checkbox_label') }}
                                    </label>

                                    @unless ($partRequest->hasBeenPaid() || $presentAsFree)
                                        <div class="mt-3 flex flex-wrap items-end gap-3">
                                            <div>
                                                <label for="shipping-override-{{ $response->id }}" class="block text-xs font-medium text-ink-muted">
                                                    {{ __('admin.request_detail.shipping_override_label') }}
                                                </label>
                                                <input
                                                    id="shipping-override-{{ $response->id }}"
                                                    type="number"
                                                    min="0"
                                                    wire:model="shippingFeeOverrides.{{ $response->id }}"
                                                    placeholder="{{ $calculatedShipping !== null ? number_format($calculatedShipping) : '' }}"
                                                    class="mt-1 block w-36 rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                                                >
                                            </div>
                                            <div class="min-w-[16rem] flex-1">
                                                <label for="shipping-override-reason-{{ $response->id }}" class="block text-xs font-medium text-ink-muted">
                                                    {{ __('admin.request_detail.shipping_override_reason_label') }}
                                                </label>
                                                <input
                                                    id="shipping-override-reason-{{ $response->id }}"
                                                    type="text"
                                                    wire:model="shippingFeeOverrideReasons.{{ $response->id }}"
                                                    placeholder="{{ __('admin.request_detail.shipping_override_reason_placeholder') }}"
                                                    class="mt-1 block w-full rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                                                >
                                            </div>
                                        </div>
                                        @error("shippingFeeOverrides.{$response->id}")
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                        @error("shippingFeeOverrideReasons.{$response->id}")
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    @endunless
                                @endunless
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @unless ($partRequest->hasBeenPaid())
                @php
                    $hasSelectablePresentableQuotes = $vendorResponses
                        ->filter(fn ($response) => ! $response->is_no_stock && ! in_array($response->id, $presentedResponseIds, true))
                        ->isNotEmpty();
                @endphp
                @if ($hasSelectablePresentableQuotes)
                    <div class="mt-4 rounded-md border border-line bg-surface-muted p-3">
                        <label class="flex items-center gap-2 text-sm font-medium text-ink">
                            <input
                                type="checkbox"
                                wire:model.live="presentAsFree"
                                class="rounded border-line text-brand-600 focus:ring-1 focus:ring-brand-500"
                            >
                            {{ __('admin.request_detail.present_as_free_label') }}
                        </label>
                        <p class="mt-1 text-xs text-ink-muted">{{ __('admin.request_detail.present_as_free_help') }}</p>
                    </div>

                    <div class="mt-4">
                        <button
                            type="button"
                            wire:click="presentSelectedQuotes"
                            wire:confirm="{{ __('admin.request_detail.present_selected_confirm') }}"
                            wire:loading.attr="disabled"
                            wire:target="presentSelectedQuotes"
                            class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ __('admin.request_detail.present_selected_button') }}
                        </button>
                    </div>
                @endif
            @endunless
            @endif
        </div>
    @endif
</div>
