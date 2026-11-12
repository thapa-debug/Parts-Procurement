<div>
    @php
        // Display-only label over the real status (never a status of its
        // own): a vendor_inquiry request with at least one real quote in
        // reads "Quotes to review". quotes_count is only loaded at
        // vendor_inquiry (RequestDetail::render()).
        $quotesToReview = $partRequest->status === \App\Enums\RequestStatus::VendorInquiry
            && ($partRequest->quotes_count ?? 0) > 0;
    @endphp

    {{-- Header: breadcrumb, part name + status pill, request meta. --}}
    <nav class="flex items-center gap-1.5 text-xs text-ink-muted" aria-label="{{ __('admin.request_detail.breadcrumb_label') }}">
        <a href="{{ route('admin.requests.index') }}" class="hover:text-ink hover:underline">{{ __('admin.request_board.title') }}</a>
        <span aria-hidden="true">/</span>
        <span class="font-mono text-ink">{{ $partRequest->request_code }}</span>
    </nav>

    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2">
        <h1 class="text-2xl font-semibold text-ink">{{ $partRequest->part_name }}</h1>

        @if ($quotesToReview)
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700"
                title="{{ __('admin.request_detail.status_title', ['status' => __('admin.request_board.status.'.$partRequest->status->value)]) }}"
            >
                <span class="size-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                {{ __('admin.request_detail.quotes_to_review') }}
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $partRequest->status->badgeClasses() }}">
                <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
                {{ __('admin.request_board.status.'.$partRequest->status->value) }}
            </span>
        @endif

        @if ($partRequest->is_url_sourced)
            <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">{{ __('admin.request_detail.url_priced_badge') }}</span>
        @endif
    </div>

    <p class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-muted">
        <span>
            <span class="font-medium text-ink">{{ $partRequest->buyer->company_name }}</span>
            @if ($partRequest->buyer->country)
                &middot; {{ $partRequest->buyer->country->name }}
            @endif
        </span>
        <span>{{ $partRequest->maker->name }} {{ $partRequest->car_model }}</span>
        <span class="font-mono text-xs">{{ $partRequest->vin }}</span>
        <span>{{ __('admin.request_detail.received_at', ['date' => $partRequest->created_at->format('Y-m-d H:i')]) }}</span>
    </p>

    <x-admin.request-stepper class="mt-5" :status="$partRequest->status" :quotes-to-review="$quotesToReview" :url-sourced="$partRequest->is_url_sourced" />

    {{-- Two-column body: main (actions + quotes) and a right sidebar
    (request info + vendor inquiry). The main column is min-w-0 so the
    quote table scrolls inside it rather than stretching the page. --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="min-w-0 [&>:first-child]:mt-0">

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

    @if ($awaitingVendorConfirmation)
        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.confirm_order_section') }}</h2>
            <p class="mt-1 text-xs text-ink-muted">{{ __('admin.request_detail.confirm_order_help') }}</p>

            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.confirm_order_vendor_label') }}</dt>
                    <dd class="mt-0.5 font-medium text-ink">{{ $vendorToConfirm?->company_name ?? __('admin.request_detail.not_provided') }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.confirm_order_delivery_method_label') }}</dt>
                    <dd class="mt-0.5 font-medium text-ink">
                        {{ $vendorToConfirm ? __('enums.delivery_method.'.$vendorToConfirm->delivery_method->value) : __('admin.request_detail.not_provided') }}
                    </dd>
                </div>
            </dl>

            @unless ($paymentGateOpen)
                <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {{ __('admin.request_detail.confirm_order_payment_pending') }}
                </p>
            @endunless

            <div class="mt-4">
                @if ($companyAddresses->isEmpty())
                    <p class="text-sm text-ink-muted">
                        {{ __('admin.request_detail.confirm_order_no_addresses') }}
                        <a href="{{ route('admin.settings') }}" class="font-medium text-brand-700 hover:text-brand-800 hover:underline">
                            {{ __('admin.request_detail.confirm_order_settings_link') }}
                        </a>
                    </p>
                @else
                    <label for="receivingAddressId" class="block text-sm font-medium text-ink">
                        {{ __('admin.request_detail.confirm_order_address_label') }} <x-required-mark />
                    </label>
                    <select
                        id="receivingAddressId"
                        wire:model="receivingAddressId"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 sm:max-w-md"
                    >
                        <option value="">{{ __('admin.request_detail.confirm_order_address_placeholder') }}</option>
                        @foreach ($companyAddresses as $address)
                            <option value="{{ $address->id }}">{{ $address->label }} -- {{ $address->prefecture }} {{ $address->city }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-ink-muted">{{ __('admin.request_detail.confirm_order_address_help') }}</p>
                    @error('receivingAddressId')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            <div class="mt-5">
                <button
                    type="button"
                    class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                    wire:click="confirmOrderToVendor"
                    wire:confirm="{{ __('admin.request_detail.confirm_order_confirm') }}"
                    wire:loading.attr="disabled"
                    wire:target="confirmOrderToVendor"
                    @disabled(! $paymentGateOpen || $companyAddresses->isEmpty())
                >
                    {{ __('admin.request_detail.confirm_order_button') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Reference-URL flow: the counterpart of the confirm-to-vendor panel
    above -- no vendor, no receiving address, the same §6.3 gate. --}}
    @if ($awaitingUrlPurchase)
        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.url_purchase_section') }}</h2>
            <p class="mt-1 text-xs text-ink-muted">{{ __('admin.request_detail.url_purchase_help') }}</p>

            @if ($partRequest->reference_url)
                <a href="{{ $partRequest->reference_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex max-w-full items-center gap-1 truncate text-sm font-medium text-brand-700 hover:underline" title="{{ $partRequest->reference_url }}">
                    {{ __('admin.request_detail.url_source_open_link') }} <span aria-hidden="true">&nearr;</span>
                </a>
            @endif

            @unless ($paymentGateOpen)
                <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {{ __('admin.request_detail.confirm_order_payment_pending') }}
                </p>
            @endunless

            <div class="mt-4">
                <label for="urlPurchaseReference" class="block text-sm font-medium text-ink">
                    {{ __('admin.request_detail.url_purchase_reference_label') }}
                </label>
                <input
                    id="urlPurchaseReference"
                    type="text"
                    wire:model="urlPurchaseReference"
                    placeholder="{{ __('admin.request_detail.url_purchase_reference_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 sm:max-w-md"
                >
                <p class="mt-1 text-xs text-ink-muted">{{ __('admin.request_detail.url_purchase_reference_help') }}</p>
                @error('urlPurchaseReference')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-5">
                <button
                    type="button"
                    class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                    wire:click="markPurchasedFromUrl"
                    wire:confirm="{{ __('admin.request_detail.url_purchase_confirm') }}"
                    wire:loading.attr="disabled"
                    wire:target="markPurchasedFromUrl"
                    @disabled(! $paymentGateOpen)
                >
                    {{ __('admin.request_detail.url_purchase_button') }}
                </button>
            </div>
        </div>
    @endif

    @if ($urlPurchased)
        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.url_purchased_section') }}</h2>

            <dl class="mt-4 text-sm">
                <dt class="text-ink-muted">
                    {{ __('admin.request_detail.url_purchased_reference_label') }}
                    <span class="text-[11px]">({{ __('admin.request_detail.admin_only_hint') }})</span>
                </dt>
                <dd class="mt-0.5 whitespace-pre-line break-words font-medium text-ink">{{ $partRequest->url_purchase_reference ?? __('admin.request_detail.not_provided') }}</dd>
            </dl>
        </div>
    @endif

    {{-- receiving_label is only ever set by ConfirmOrderToVendorAction --
    always read the snapshot, never the live CompanyAddress. --}}
    @if ($partRequest->receiving_label)
        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.order_confirmed_section') }}</h2>

            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.order_confirmed_vendor_label') }}</dt>
                    <dd class="mt-0.5 font-medium text-ink">{{ $confirmedVendor?->company_name ?? __('admin.request_detail.not_provided') }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.order_confirmed_deliver_to_label') }}</dt>
                    <dd class="mt-0.5 font-medium text-ink">
                        <x-receiving-address :part-request="$partRequest" />
                    </dd>
                </div>
            </dl>
        </div>
    @endif

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
    @endif

    {{-- Reference-URL flow: the alternative to the broadcast above, for a
    `new` request with a reference URL (SetUrlPriceAction). The preview is
    the same PricingService/ShippingCalculator the action uses. --}}
    @if ($canSourceFromUrl)
        @php
            $previewPricing = $urlPricePreview['pricing'];
            $previewShipping = filled($urlShippingFeeOverride) && ctype_digit($urlShippingFeeOverride)
                ? (int) $urlShippingFeeOverride
                : $urlPricePreview['shipping'];
            $previewWeightEntered = is_numeric($urlWeightKg) && (float) $urlWeightKg > 0;
        @endphp
        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="max-w-2xl">
                    <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.url_source_section') }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">{{ __('admin.request_detail.url_source_help') }}</p>
                </div>
                <a href="{{ $partRequest->reference_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-brand-700 hover:underline" title="{{ $partRequest->reference_url }}">
                    {{ __('admin.request_detail.url_source_open_link') }} <span aria-hidden="true">&nearr;</span>
                </a>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-[minmax(0,1fr)_16rem]">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="urlCost" class="block text-sm font-medium text-ink">
                            {{ __('admin.request_detail.url_cost_label') }} <x-required-mark />
                        </label>
                        <input
                            id="urlCost"
                            type="number"
                            min="1"
                            step="1"
                            inputmode="numeric"
                            wire:model.live.debounce.400ms="urlCost"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        <p class="mt-1 text-xs text-ink-muted">{{ __('admin.request_detail.url_cost_help') }}</p>
                        @error('urlCost')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="urlWeightKg" class="block text-sm font-medium text-ink">
                            {{ __('admin.request_detail.url_weight_label') }} <x-required-mark />
                        </label>
                        <input
                            id="urlWeightKg"
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            wire:model.live.debounce.400ms="urlWeightKg"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        <p class="mt-1 text-xs text-ink-muted">{{ __('admin.request_detail.url_weight_help') }}</p>
                        @error('urlWeightKg')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="urlShippingFeeOverride" class="block text-sm font-medium text-ink">
                            {{ __('admin.request_detail.shipping_override_label') }}
                        </label>
                        <input
                            id="urlShippingFeeOverride"
                            type="number"
                            min="0"
                            step="1"
                            inputmode="numeric"
                            wire:model.live.debounce.400ms="urlShippingFeeOverride"
                            placeholder="{{ $urlPricePreview['shipping'] !== null ? number_format($urlPricePreview['shipping']) : '' }}"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        @error('urlShippingFeeOverride')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="urlShippingFeeOverrideReason" class="block text-sm font-medium text-ink">
                            {{ __('admin.request_detail.shipping_override_reason_label') }}
                        </label>
                        <input
                            id="urlShippingFeeOverrideReason"
                            type="text"
                            wire:model="urlShippingFeeOverrideReason"
                            placeholder="{{ __('admin.request_detail.shipping_override_reason_placeholder') }}"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        @error('urlShippingFeeOverrideReason')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Live preview: what the buyer will be shown. --}}
                <div class="rounded-md border border-line bg-surface-muted p-4 text-sm" wire:loading.class="opacity-60" wire:target="urlCost,urlWeightKg,urlShippingFeeOverride">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ __('admin.request_detail.url_preview_heading') }}</p>

                    @if ($previewPricing === null || ($previewShipping === null && ! $previewWeightEntered))
                        <p class="mt-2 text-ink-muted">{{ __('admin.request_detail.url_preview_incomplete') }}</p>
                    @endif

                    <dl class="mt-2 space-y-1.5">
                        @if ($previewPricing !== null)
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-ink-muted">{{ __('admin.request_detail.markup_row') }}</dt>
                                <dd class="text-right text-xs text-ink-muted">
                                    <span class="font-mono">{{ $previewPricing['applied_rate'] }} %</span>
                                    &middot; {{ __('admin.request_detail.margin_amount', ['amount' => number_format($previewPricing['margin'])]) }}
                                    @if ($previewPricing['margin'] === $previewPricing['applied_min_fee'])
                                        ({{ __('admin.request_detail.min_fee_applied') }})
                                    @endif
                                </dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-ink-muted">{{ __('admin.request_detail.buyer_price_column') }}</dt>
                                <dd class="font-mono font-semibold text-ink">¥{{ number_format($previewPricing['buyer_price']) }}</dd>
                            </div>
                        @endif

                        @if ($previewShipping !== null)
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-ink-muted">{{ __('admin.request_detail.shipping_fee_column') }}</dt>
                                <dd class="font-mono text-ink">
                                    ¥{{ number_format($previewShipping) }}
                                    @if (filled($urlShippingFeeOverride))
                                        <span class="font-sans text-xs text-ink-muted">({{ __('admin.request_detail.shipping_overridden_note') }})</span>
                                    @endif
                                </dd>
                            </div>
                        @elseif ($previewWeightEntered)
                            <p class="text-xs text-amber-700">{{ __('admin.request_detail.url_preview_no_bracket') }}</p>
                        @endif

                        @if ($previewPricing !== null && $previewShipping !== null)
                            <div class="flex items-baseline justify-between gap-2 border-t border-line pt-1.5">
                                <dt class="font-medium text-ink">{{ __('admin.request_detail.buyer_total_row') }}</dt>
                                <dd class="font-mono text-base font-semibold text-ink">¥{{ number_format($previewPricing['buyer_price'] + $previewShipping) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="mt-5">
                <button
                    type="button"
                    wire:click="setUrlPrice"
                    wire:confirm="{{ __('admin.request_detail.url_price_confirm') }}"
                    wire:loading.attr="disabled"
                    wire:target="setUrlPrice"
                    class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ __('admin.request_detail.url_price_button') }}
                </button>
            </div>
        </div>
    @endif

    {{-- A URL-sourced request has no vendor quotes: its frozen price
    snapshot (§6.2) is shown here instead of the comparison table. --}}
    @if ($partRequest->is_url_sourced)
        @php
            $urlMargin = $partRequest->buyer_price - $partRequest->cost_price;
        @endphp
        <section class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.url_priced_section') }}</h2>
                <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ __('admin.request_detail.url_priced_badge') }}</span>
            </div>
            <p class="mt-1 text-sm text-ink-muted">{{ __('admin.request_detail.url_priced_help') }}</p>

            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.cost_price_column') }} <span class="text-[11px]">({{ __('admin.request_detail.admin_only_hint') }})</span></dt>
                    <dd class="mt-0.5 font-mono font-semibold text-ink">¥{{ number_format($partRequest->cost_price) }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.markup_row') }}</dt>
                    <dd class="mt-0.5 text-ink">
                        <span class="font-mono">{{ $partRequest->applied_rate }} %</span>
                        <span class="text-xs text-ink-muted">
                            &middot; {{ __('admin.request_detail.margin_amount', ['amount' => number_format($urlMargin)]) }}
                            @if ($urlMargin === $partRequest->applied_min_fee)
                                ({{ __('admin.request_detail.min_fee_applied') }})
                            @endif
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.buyer_price_column') }}</dt>
                    <dd class="mt-0.5 font-mono text-base font-semibold text-ink">¥{{ number_format($partRequest->buyer_price) }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.weight_column') }}</dt>
                    <dd class="mt-0.5 font-mono text-ink">{{ rtrim(rtrim(number_format((float) $partRequest->url_weight_kg, 2, '.', ''), '0'), '.') }} kg</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.shipping_fee_column') }} <span class="text-[11px]">({{ __('enums.shipping_method.standard') }})</span></dt>
                    <dd class="mt-0.5 font-mono text-ink">
                        ¥{{ number_format($partRequest->url_shipping_fee) }}
                        @if ($partRequest->url_shipping_fee_overridden)
                            <span class="font-sans text-xs text-ink-muted">({{ __('admin.request_detail.shipping_overridden_note') }})</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('admin.request_detail.buyer_total_row') }}</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-ink">¥{{ number_format($partRequest->buyer_price + $partRequest->url_shipping_fee) }}</dd>
                </div>
                @if ($partRequest->url_shipping_fee_overridden)
                    <div class="col-span-2 sm:col-span-3">
                        <dt class="text-ink-muted">{{ __('admin.request_detail.url_override_reason_label') }}</dt>
                        <dd class="mt-0.5 whitespace-pre-line break-words text-ink">{{ $partRequest->url_shipping_fee_override_reason }}</dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    {{-- Vendor quotes: one column per real quote, full page width,
    scrolling sideways when there are many vendors. Restyle only -- every
    input keeps its original wire: binding and the conditions it rendered
    under before. Vendor name and cost are shown here because this is the
    admin's own screen; the buyer side builds its own isolated data. A
    URL-sourced request was never broadcast, so it has no quotes table. --}}
    @if ($partRequest->status !== \App\Enums\RequestStatus::New && ! $partRequest->is_url_sourced)
        @php
            $quoteResponses = $vendorResponses->reject(fn ($response) => $response->is_no_stock)->values();
            $noStockResponses = $vendorResponses->filter(fn ($response) => $response->is_no_stock)->values();
            $choiceLocked = $partRequest->quoteChoiceLocked();

            // Everything each column shows, worked out once from the data
            // the component already passes (no new data). A presented
            // quote shows its frozen snapshot; an unpresented one shows the
            // live PricingService/ShippingCalculator preview, as before.
            $columns = $quoteResponses->map(function ($response, $index) use ($vendorResponsePricing, $vendorResponseShipping, $presentedResponseIds, $presentedQuotesByResponseId, $partRequest, $confirmedPayment) {
                $pricing = $vendorResponsePricing->get($response->id);
                $presentedQuote = $presentedQuotesByResponseId->get($response->id);
                $isPresented = in_array($response->id, $presentedResponseIds, true);
                $isBuyerSelected = $partRequest->selected_response_id === $response->id;
                $isPaidAndSelected = $isBuyerSelected && $confirmedPayment !== null;
                $calculatedShipping = $vendorResponseShipping->get($response->id);

                $buyerPrice = $presentedQuote?->buyer_price ?? $pricing['buyer_price'] ?? null;
                $shipping = $presentedQuote ? $presentedQuote->shipping_fee : $calculatedShipping;

                return [
                    'response' => $response,
                    'letter' => chr(65 + $index % 26),
                    'presentedQuote' => $presentedQuote,
                    'isPresented' => $isPresented,
                    'isBuyerSelected' => $isBuyerSelected,
                    'isPaidAndSelected' => $isPaidAndSelected,
                    'isFree' => (bool) $presentedQuote?->is_free,
                    'calculatedShipping' => $calculatedShipping,
                    'rate' => $presentedQuote?->applied_rate ?? $pricing['applied_rate'] ?? null,
                    'minFee' => $presentedQuote?->applied_min_fee ?? $pricing['applied_min_fee'] ?? null,
                    'margin' => $presentedQuote
                        ? ($presentedQuote->is_free ? null : $presentedQuote->buyer_price - $presentedQuote->cost_price)
                        : ($pricing['margin'] ?? null),
                    'buyerPrice' => $buyerPrice,
                    'shipping' => $shipping,
                    'total' => $buyerPrice !== null && $shipping !== null ? $buyerPrice + $shipping : null,
                    // Same precedence as before: an open payment gate (real
                    // or 無償) wins over "selected", which wins over
                    // "presented". Cyan for free-confirmed, so it never
                    // blurs with the emerald "Free" tag or green "Paid".
                    'tint' => match (true) {
                        $isPaidAndSelected && $partRequest->is_free => 'bg-cyan-50',
                        $isPaidAndSelected => 'bg-green-50',
                        $isBuyerSelected => 'bg-brand-50',
                        $isPresented => 'bg-blue-50/60',
                        default => '',
                    },
                    'accent' => match (true) {
                        $isPaidAndSelected && $partRequest->is_free => 'border-t-cyan-400',
                        $isPaidAndSelected => 'border-t-green-500',
                        $isBuyerSelected => 'border-t-brand-600',
                        $isPresented => 'border-t-blue-300',
                        default => 'border-t-transparent',
                    },
                ];
            });

            $formatNumber = fn ($value) => $value === null ? null : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

            // The quotes that can still be ticked for presenting (real
            // quotes, not yet presented, choice not locked) -- exactly the
            // ones with a checkbox, and what select-all toggles. No-stock
            // replies never get a column, so they're never in here.
            $presentableIds = $choiceLocked
                ? []
                : $columns->reject(fn ($column) => $column['isPresented'])->map(fn ($column) => (string) $column['response']->id)->values()->all();
            $lettersById = $columns->mapWithKeys(fn ($column) => [(string) $column['response']->id => $column['letter']])->all();
        @endphp

        {{-- Alpine here only reads/writes the existing
        selectedResponseIdsToPresent (deferred, like the checkboxes'
        wire:model) -- no new server state. It drives the column tint,
        select-all, and the bottom bar's count. --}}
        <section
            class="mt-6 rounded-lg border border-line bg-surface shadow-sm"
            x-data="{
                presentable: @js($presentableIds),
                letters: @js($lettersById),
                selectedIds() { return (this.$wire.selectedResponseIdsToPresent || []).map(String) },
                checked(id) { return this.selectedIds().includes(String(id)) },
                selectedPresentable() { return this.presentable.filter((id) => this.checked(id)) },
                allChecked() { return this.presentable.length > 0 && this.selectedPresentable().length === this.presentable.length },
                someChecked() { const count = this.selectedPresentable().length; return count > 0 && count < this.presentable.length },
                toggleAll(on) { this.$wire.selectedResponseIdsToPresent = on ? [...this.presentable] : [] },
                selectedLetters() { return this.selectedPresentable().map((id) => this.letters[id]).sort().join(', ') },
            }"
        >
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.compare_section') }}</h2>
                    <p class="mt-0.5 text-sm text-ink-muted">
                        @if ($partRequest->status === \App\Enums\RequestStatus::AwaitingPayment)
                            {{ __('admin.request_detail.compare_awaiting_payment_help') }}
                        @elseif ($partRequest->hasBeenPaid())
                            {{ __('admin.request_detail.compare_locked_help') }}
                        @else
                            {{ __('admin.request_detail.compare_help') }}
                        @endif
                    </p>
                </div>

                {{-- Inquiry progress (moved here from the header): real
                quotes out of invited vendors, plus no-stock and pending. --}}
                @if ($partRequest->status === \App\Enums\RequestStatus::VendorInquiry)
                    @php
                        $pendingReplies = max($partRequest->invited_vendors_count - $partRequest->quotes_count - $partRequest->no_stock_replies_count, 0);
                    @endphp
                    <div class="flex flex-wrap items-center gap-1.5">
                        <x-admin.quote-response-count
                            :quotes="$partRequest->quotes_count"
                            :invited="$partRequest->invited_vendors_count"
                            :no-stock="$partRequest->no_stock_replies_count"
                        />
                        @if ($pendingReplies > 0)
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                {{ __('admin.request_detail.pending_replies', ['count' => $pendingReplies]) }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            @error('presentQuote')
                <p class="mx-5 mt-4 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if ($vendorResponses->isEmpty())
                <p class="m-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    {{ __('admin.request_detail.no_vendor_responses') }}
                </p>
            @else
                @if ($columns->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max border-separate border-spacing-0 text-sm">
                            <thead>
                                <tr>
                                    <th scope="col" class="sticky left-0 z-10 w-40 min-w-40 border-b border-line bg-surface px-4 py-3 text-left align-top text-xs font-medium uppercase tracking-wide text-ink-muted">
                                        {{ __('admin.request_detail.quote_row') }}

                                        {{-- Select all presentable quotes; checked when
                                        all are, indeterminate when only some are. --}}
                                        @if (count($presentableIds) > 0)
                                            <label class="mt-2 flex items-center gap-2 text-xs font-medium normal-case tracking-normal text-ink">
                                                <input
                                                    type="checkbox"
                                                    data-select-all-quotes
                                                    :checked="allChecked()"
                                                    :indeterminate="someChecked()"
                                                    x-on:change="toggleAll($event.target.checked)"
                                                    class="rounded border-line text-brand-600 focus:ring-1 focus:ring-brand-500"
                                                >
                                                {{ __('admin.request_detail.select_all_quotes') }}
                                            </label>
                                        @endif
                                    </th>
                                    @foreach ($columns as $column)
                                        @php $response = $column['response']; @endphp
                                        <th
                                            scope="col"
                                            wire:key="quote-head-{{ $response->id }}"
                                            class="w-72 min-w-72 border-b border-t-2 border-b-line px-4 py-3 text-left align-top font-normal {{ $column['accent'] }} {{ $column['tint'] }}"
                                            @unless ($column['isPresented']) :class="checked({{ $response->id }}) && 'bg-brand-50/70 border-t-brand-600!'" @endunless
                                        >
                                            <div class="flex items-start gap-2">
                                                @unless ($column['isPresented'])
                                                    <input
                                                        type="checkbox"
                                                        wire:model="selectedResponseIdsToPresent"
                                                        value="{{ $response->id }}"
                                                        {{ $choiceLocked ? 'disabled' : '' }}
                                                        aria-label="{{ __('admin.request_detail.present_checkbox_label') }} -- {{ $column['letter'] }}"
                                                        class="mt-0.5 rounded border-line text-brand-600 focus:ring-1 focus:ring-brand-500 disabled:opacity-50"
                                                    >
                                                @endunless
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-ink">
                                                        <span class="text-brand-700">{{ $column['letter'] }}</span>
                                                        &middot; {{ $response->vendor->company_name }}
                                                    </p>
                                                    <p class="mt-0.5 text-xs text-ink-muted">
                                                        {{ $response->vendor->contact_person }}
                                                        &middot; {{ __('admin.request_detail.replied_at', ['date' => $response->created_at->format('m-d H:i')]) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>

                            <tbody>
                                {{-- Photos --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.photos_row') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        <td class="border-b border-line px-4 py-3 align-top {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $column['response']->id }}) && 'bg-brand-50/70'" @endunless>
                                            <x-photo-gallery :photos="$column['response']->photos->map(fn ($photo) => $photo->url())->all()" />
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- Quality rank --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.quality_rank_column') }}</span>
                                        <span class="block text-[11px] text-ink-muted">{{ __('admin.request_detail.quality_rank_hint') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        @php $rankLabel = __('enums.quality_rank.'.$column['response']->quality_rank->value); @endphp
                                        <td class="border-b border-line px-4 py-3 align-top {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $column['response']->id }}) && 'bg-brand-50/70'" @endunless>
                                            <span class="flex items-start gap-2">
                                                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-md bg-surface-muted text-xs font-semibold text-ink">{{ strtoupper($column['response']->quality_rank->value) }}</span>
                                                <span class="text-ink">{{ \Illuminate\Support\Str::after($rankLabel, ' -- ') }}</span>
                                            </span>
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- Vendor cost (admin only) --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.cost_price_column') }}</span>
                                        <span class="block text-[11px] text-ink-muted">{{ __('admin.request_detail.admin_only_hint') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        <td class="border-b border-line px-4 py-3 align-top font-mono font-semibold text-ink {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $column['response']->id }}) && 'bg-brand-50/70'" @endunless>
                                            ¥{{ number_format($column['response']->cost_price) }}
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- Markup --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.markup_row') }}</span>
                                        <span class="block text-[11px] text-ink-muted">{{ __('admin.request_detail.markup_hint') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        <td class="border-b border-line px-4 py-3 align-top text-ink {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $column['response']->id }}) && 'bg-brand-50/70'" @endunless>
                                            @if ($column['isFree'])
                                                <span class="text-ink-muted">{{ __('admin.request_detail.markup_free') }}</span>
                                            @elseif ($column['rate'] !== null && $column['margin'] !== null)
                                                <span class="font-mono">{{ $column['rate'] }} %</span>
                                                <span class="text-xs text-ink-muted">
                                                    &middot; {{ __('admin.request_detail.margin_amount', ['amount' => number_format($column['margin'])]) }}
                                                    @if ($column['minFee'] !== null && $column['margin'] === $column['minFee'])
                                                        ({{ __('admin.request_detail.min_fee_applied') }})
                                                    @endif
                                                </span>
                                            @else
                                                {{ __('admin.request_detail.not_provided') }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- Buyer price --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.buyer_price_column') }}</span>
                                        <span class="block text-[11px] text-ink-muted">{{ __('admin.request_detail.part_only_hint') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        <td class="border-b border-line px-4 py-3 align-top font-mono text-base font-semibold text-ink {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $column['response']->id }}) && 'bg-brand-50/70'" @endunless>
                                            {{ $column['buyerPrice'] !== null ? '¥'.number_format($column['buyerPrice']) : __('admin.request_detail.not_provided') }}
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- Estimated shipping (+ the override inputs, unchanged) --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.shipping_fee_column') }}</span>
                                        <span class="block text-[11px] text-ink-muted">{{ __('admin.request_detail.shipping_hint') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        @php $response = $column['response']; @endphp
                                        <td class="border-b border-line px-4 py-3 align-top {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $response->id }}) && 'bg-brand-50/70'" @endunless>
                                            <span class="font-mono text-ink">{{ $column['shipping'] !== null ? '¥'.number_format($column['shipping']) : __('admin.request_detail.not_provided') }}</span>
                                            @if ($column['presentedQuote']?->shipping_fee_overridden)
                                                <span class="ml-1 text-xs text-ink-muted">({{ __('admin.request_detail.shipping_overridden_note') }})</span>
                                            @endif

                                            @unless ($column['isPresented'] || $choiceLocked || $presentAsFree)
                                                <div class="mt-2 space-y-2">
                                                    <div>
                                                        <label for="shipping-override-{{ $response->id }}" class="block text-xs font-medium text-ink-muted">
                                                            {{ __('admin.request_detail.shipping_override_label') }}
                                                        </label>
                                                        <input
                                                            id="shipping-override-{{ $response->id }}"
                                                            type="number"
                                                            min="0"
                                                            wire:model="shippingFeeOverrides.{{ $response->id }}"
                                                            placeholder="{{ $column['calculatedShipping'] !== null ? number_format($column['calculatedShipping']) : '' }}"
                                                            class="mt-1 block w-full rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                                                        >
                                                    </div>
                                                    <div>
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
                                                    @error("shippingFeeOverrides.{$response->id}")
                                                        <p class="text-xs text-red-600">{{ $message }}</p>
                                                    @enderror
                                                    @error("shippingFeeOverrideReasons.{$response->id}")
                                                        <p class="text-xs text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            @endunless
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- Buyer total --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.buyer_total_row') }}</span>
                                        <span class="block text-[11px] text-ink-muted">{{ __('admin.request_detail.buyer_total_hint') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        <td class="border-b border-line px-4 py-3 align-top font-mono font-semibold text-ink {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $column['response']->id }}) && 'bg-brand-50/70'" @endunless>
                                            {{ $column['total'] !== null ? '¥'.number_format($column['total']) : __('admin.request_detail.not_provided') }}
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- Lead time --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.lead_time_column') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        <td class="border-b border-line px-4 py-3 align-top text-ink {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $column['response']->id }}) && 'bg-brand-50/70'" @endunless>
                                            {{ __('enums.lead_time.'.$column['response']->lead_time->value) }}
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- Weight --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.weight_column') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        <td class="border-b border-line px-4 py-3 align-top font-mono text-ink {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $column['response']->id }}) && 'bg-brand-50/70'" @endunless>
                                            {{ $column['response']->weight_kg !== null ? $formatNumber($column['response']->weight_kg).' kg' : __('admin.request_detail.not_provided') }}
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- Dimensions --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.dimensions_row') }}</span>
                                        <span class="block text-[11px] text-ink-muted">{{ __('admin.request_detail.dimensions_hint') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        @php $r = $column['response']; @endphp
                                        <td class="border-b border-line px-4 py-3 align-top font-mono text-ink {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $r->id }}) && 'bg-brand-50/70'" @endunless>
                                            @if ($r->length_cm !== null && $r->width_cm !== null && $r->height_cm !== null)
                                                {{ $formatNumber($r->length_cm) }} &times; {{ $formatNumber($r->width_cm) }} &times; {{ $formatNumber($r->height_cm) }} cm
                                            @else
                                                {{ __('admin.request_detail.not_provided') }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- Condition notes --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-line bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.condition_notes_row') }}</span>
                                        <span class="block text-[11px] text-ink-muted">{{ __('admin.request_detail.condition_notes_hint') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        <td class="whitespace-normal border-b border-line px-4 py-3 align-top text-ink {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $column['response']->id }}) && 'bg-brand-50/70'" @endunless>
                                            {{ $column['response']->comment ?: __('admin.request_detail.not_provided') }}
                                        </td>
                                    @endforeach
                                </tr>

                                {{-- This quote's status --}}
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 bg-surface px-4 py-3 text-left align-top font-normal">
                                        <span class="block text-xs font-medium text-ink">{{ __('admin.request_detail.quote_status_row') }}</span>
                                        <span class="block text-[11px] text-ink-muted">{{ __('admin.request_detail.quote_status_hint') }}</span>
                                    </th>
                                    @foreach ($columns as $column)
                                        <td class="px-4 py-3 align-top {{ $column['tint'] }}" @unless ($column['isPresented']) :class="checked({{ $column['response']->id }}) && 'bg-brand-50/70'" @endunless>
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                @if ($column['isPresented'])
                                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                                        {{ __('admin.request_detail.presented_badge') }}
                                                    </span>

                                                    {{-- "Free (無償)" marks what the quote WOULD cost
                                                    the buyer if chosen -- once confirmed, the
                                                    free-confirmed badge takes over instead. --}}
                                                    @if ($column['isFree'] && ! $column['isPaidAndSelected'])
                                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                                            {{ __('admin.request_detail.free_badge') }}
                                                        </span>
                                                    @endif

                                                    @if ($column['isBuyerSelected'])
                                                        <span class="inline-flex rounded-full bg-brand-100 px-2.5 py-1 text-xs font-medium text-brand-700">
                                                            {{ __('admin.request_detail.buyer_selected_badge') }}
                                                        </span>
                                                    @endif

                                                    @if ($column['isPaidAndSelected'])
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
                                                @else
                                                    <span class="text-xs font-medium text-brand-700" x-show="checked({{ $column['response']->id }})" x-cloak>{{ __('admin.request_detail.checked_to_present') }}</span>
                                                    <span class="text-xs text-ink-muted" x-show="! checked({{ $column['response']->id }})">{{ __('admin.request_detail.not_presented') }}</span>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- No-stock replies: no quote to compare, so a compact
                line rather than an empty column. --}}
                @if ($noStockResponses->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2 border-t border-line px-5 py-3 text-sm">
                        <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">{{ __('admin.request_detail.no_stock_badge') }}</span>
                        @foreach ($noStockResponses as $response)
                            <span wire:key="no-stock-{{ $response->id }}" class="text-ink">
                                {{ $response->vendor->company_name }}
                                <span class="text-xs text-ink-muted">{{ __('admin.request_detail.replied_at', ['date' => $response->created_at->format('m-d H:i')]) }}</span>@unless ($loop->last),@endunless
                            </span>
                        @endforeach
                    </div>
                @endif

                {{-- Bottom action bar: free flag + present button --
                unchanged behaviour, shown under the same conditions as
                before. Sticks to the bottom of the screen while the quotes
                are in view. --}}
                @unless ($choiceLocked)
                    @php
                        $hasSelectablePresentableQuotes = $vendorResponses
                            ->filter(fn ($response) => ! $response->is_no_stock && ! in_array($response->id, $presentedResponseIds, true))
                            ->isNotEmpty();
                    @endphp
                    @if ($hasSelectablePresentableQuotes)
                        <div class="sticky bottom-4 z-20 m-4 rounded-lg border border-line bg-surface p-4 shadow-lg">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span
                                        class="flex size-8 shrink-0 items-center justify-center rounded-md bg-surface-muted text-sm font-semibold text-ink"
                                        :class="selectedPresentable().length > 0 && 'bg-brand-50 text-brand-700'"
                                        x-text="selectedPresentable().length"
                                    >0</span>
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-semibold text-ink"
                                            x-text="selectedPresentable().length === 0
                                                ? @js(__('admin.request_detail.present_bar_none', ['buyer' => $partRequest->buyer->company_name]))
                                                : (selectedPresentable().length === 1 ? @js(__('admin.request_detail.present_bar_selected_one', ['buyer' => $partRequest->buyer->company_name])) : @js(__('admin.request_detail.present_bar_selected_many', ['buyer' => $partRequest->buyer->company_name]))).replace(':letters', selectedLetters())"
                                        >{{ __('admin.request_detail.present_bar_none', ['buyer' => $partRequest->buyer->company_name]) }}</p>
                                        <p class="mt-0.5 flex items-center gap-1 text-xs text-ink-muted">
                                            <svg class="size-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd"/><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/></svg>
                                            {{ __('admin.request_detail.present_bar_isolation_note') }}
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    wire:click="presentSelectedQuotes"
                                    wire:confirm="{{ __('admin.request_detail.present_selected_confirm') }}"
                                    wire:loading.attr="disabled"
                                    wire:target="presentSelectedQuotes"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <span x-text="selectedPresentable().length === 0
                                        ? @js(__('admin.request_detail.present_selected_button'))
                                        : (selectedPresentable().length === 1 ? @js(__('admin.request_detail.present_count_button_one')) : @js(__('admin.request_detail.present_count_button_many')).replace(':count', selectedPresentable().length))"
                                    >{{ __('admin.request_detail.present_selected_button') }}</span>
                                    <span aria-hidden="true">&rarr;</span>
                                </button>
                            </div>

                            <div class="mt-3 border-t border-line pt-3">
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
                        </div>
                    @endif
                @endunless
            @endif
        </section>
    @endif

    </div>

    {{-- Right sidebar: request info + vendor inquiry (打診) status.
    Read-only, built from data the component already passes. --}}
    <aside class="space-y-4 lg:sticky lg:top-4 lg:self-start">
        <section class="rounded-lg border border-line bg-surface p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-ink">{{ __('admin.request_detail.request_info_section') }}</h2>

            <dl class="mt-3 space-y-2 text-sm">
                @php
                    $infoRows = [
                        [__('admin.request_detail.buyer_label'), $partRequest->buyer->company_name, 'font-medium'],
                        [__('admin.request_detail.country_label'), $partRequest->buyer->country?->name, ''],
                        [__('admin.request_detail.maker_label'), $partRequest->maker->name, ''],
                        [__('admin.request_detail.car_model_label'), $partRequest->car_model, ''],
                        [__('admin.request_detail.vin_label'), $partRequest->vin, 'font-mono text-xs'],
                        [__('admin.request_detail.part_name_label'), $partRequest->part_name, 'font-medium'],
                        [__('admin.request_detail.part_type_label'), __('admin.request_board.part_type.'.$partRequest->part_type->value), ''],
                        [__('admin.request_detail.oem_part_number_label'), $partRequest->oem_part_number, 'font-mono text-xs'],
                    ];
                @endphp
                @foreach ($infoRows as [$label, $value, $valueClass])
                    <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-2">
                        <dt class="text-ink-muted">{{ $label }}</dt>
                        <dd class="break-words text-ink {{ $valueClass }}">{{ $value ?? __('admin.request_detail.not_provided') }}</dd>
                    </div>
                @endforeach

                {{-- Where it actually ships, once checkout has snapshotted it. --}}
                @if ($partRequest->shipping_country)
                    <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-2">
                        <dt class="text-ink-muted">{{ __('admin.request_detail.ships_to_label') }}</dt>
                        <dd class="text-ink">{{ $partRequest->shipping_city }}, {{ $partRequest->shipping_country }}</dd>
                    </div>
                @endif

                <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-2">
                    <dt class="text-ink-muted">{{ __('admin.request_detail.reference_url_label') }}</dt>
                    <dd class="min-w-0 text-ink">
                        @if ($partRequest->reference_url)
                            <a href="{{ $partRequest->reference_url }}" target="_blank" rel="noopener noreferrer" class="block truncate text-brand-700 hover:underline" title="{{ $partRequest->reference_url }}">
                                {{ $partRequest->reference_url }}
                            </a>
                        @else
                            {{ __('admin.request_detail.not_provided') }}
                        @endif
                    </dd>
                </div>

                <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-2">
                    <dt class="text-ink-muted">{{ __('admin.request_detail.requested_at_label') }}</dt>
                    <dd class="text-ink">{{ $partRequest->created_at->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>

            @if ($partRequest->memo)
                <div class="mt-3">
                    <p class="text-xs text-ink-muted">{{ __('admin.request_detail.memo_label') }}</p>
                    <p class="mt-1 whitespace-pre-line rounded-md bg-surface-muted px-3 py-2 text-sm text-ink">{{ $partRequest->memo }}</p>
                </div>
            @endif
        </section>

        {{-- Who the request went to, and whether each has replied. Only
        once it has been broadcast (invitedVendors is empty at `new`). --}}
        @if ($invitedVendors->isNotEmpty())
            @php
                $responsesByVendorId = $vendorResponses->keyBy('vendor_id');
                $firstInvitedAt = $invitedVendors->min(fn ($vendor) => $vendor->pivot->invited_at);
            @endphp
            <section class="rounded-lg border border-line bg-surface p-5 shadow-sm">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold text-ink">{{ __('admin.request_detail.inquiry_panel_section') }}</h2>
                    <span class="text-xs text-ink-muted">{{ __('admin.request_detail.inquiry_sent_at', ['date' => $firstInvitedAt->format('m-d H:i')]) }}</span>
                </div>

                <ul class="mt-3 divide-y divide-line">
                    @foreach ($invitedVendors as $vendor)
                        @php $reply = $responsesByVendorId->get($vendor->id); @endphp
                        <li wire:key="inquiry-vendor-{{ $vendor->id }}" class="flex items-start justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink">{{ $vendor->company_name }}</p>
                                <p class="text-xs text-ink-muted">
                                    {{ $vendor->contact_person }} &middot;
                                    @if ($reply)
                                        {{ __('admin.request_detail.replied_at', ['date' => $reply->created_at->format('m-d H:i')]) }}
                                    @else
                                        {{ __('admin.request_detail.invited_at', ['date' => $vendor->pivot->invited_at->format('m-d H:i')]) }}
                                    @endif
                                </p>
                            </div>
                            @if ($reply === null)
                                <span class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ __('admin.request_detail.vendor_reply.pending') }}</span>
                            @elseif ($reply->is_no_stock)
                                <span class="shrink-0 rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-ink-muted">{{ __('admin.request_detail.vendor_reply.no_stock') }}</span>
                            @else
                                <span class="shrink-0 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">{{ __('admin.request_detail.vendor_reply.quoted') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </aside>
    </div>
</div>
