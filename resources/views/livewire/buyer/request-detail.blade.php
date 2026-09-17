<div class="max-w-3xl">
    <a href="{{ route('buyer.requests.index') }}" class="text-sm text-ink-muted hover:text-ink">
        &larr; {{ __('buyer.request_detail.back_link') }}
    </a>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-ink">{{ $partRequest->request_code }}</h1>
        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $partRequest->status->badgeClasses() }}">
            {{ __('buyer.request_list.status.'.$partRequest->status->value) }}
        </span>
    </div>

    {{-- shipping_method is set by SelectQuoteAction, the moment the buyer
    picks a quote -- present as soon as one's been chosen, free or paid
    alike, so this guard is really just hasBeenPaid() with a defensive
    null-check against a request paid for before that column existed. --}}
    @if ($partRequest->hasBeenPaid() && $partRequest->shipping_method)
        <x-paid-status-banner
            :heading="$partRequest->is_free ? __('buyer.request_detail.paid_banner_heading_free') : __('buyer.request_detail.paid_banner_heading')"
            :body="$partRequest->is_free ? __('buyer.request_detail.paid_banner_body_free') : __('buyer.request_detail.paid_banner_body')"
        />

        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">
                {{ $partRequest->is_free ? __('buyer.request_detail.payment_summary_section_free') : __('buyer.request_detail.payment_summary_section') }}
            </h2>

            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-ink-muted">{{ __('buyer.request_detail.payment_summary_part_price') }}</dt>
                    <dd class="mt-0.5 font-mono font-medium text-ink">¥{{ number_format($partRequest->buyer_price) }}</dd>
                </div>

                <div>
                    <dt class="text-ink-muted">{{ __('buyer.request_detail.payment_summary_shipping_fee') }}</dt>
                    <dd class="mt-0.5 font-mono font-medium text-ink">¥{{ number_format($partRequest->shipping_fee) }}</dd>
                </div>

                <div>
                    <dt class="text-ink-muted">{{ __('buyer.request_detail.payment_summary_shipping_method') }}</dt>
                    <dd class="mt-0.5 font-medium text-ink">{{ __('enums.shipping_method.'.$partRequest->shipping_method->value) }}</dd>
                </div>

                <div>
                    <dt class="text-ink-muted">
                        {{ $partRequest->is_free ? __('buyer.request_detail.payment_summary_total_free') : __('buyer.request_detail.payment_summary_total') }}
                    </dt>
                    <dd class="mt-0.5 text-lg font-semibold text-ink">¥{{ number_format($partRequest->buyer_price + $partRequest->shipping_fee) }}</dd>
                </div>

                <div class="sm:col-span-2">
                    <dt class="text-ink-muted">{{ __('buyer.request_detail.payment_summary_shipping_to') }}</dt>
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
        <h2 class="text-base font-semibold text-ink">{{ __('buyer.request_detail.details_section') }}</h2>

        <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-ink-muted">{{ __('buyer.request_detail.part_type_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ __('buyer.request_detail.part_type.'.$partRequest->part_type->value) }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('buyer.request_detail.maker_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->maker->name }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('buyer.request_detail.car_model_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->car_model }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('buyer.request_detail.vin_label') }}</dt>
                <dd class="mt-0.5 font-mono font-medium text-ink">{{ $partRequest->vin }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('buyer.request_detail.oem_part_number_label') }}</dt>
                <dd class="mt-0.5 font-mono font-medium text-ink">{{ $partRequest->oem_part_number ?? __('buyer.request_detail.not_provided') }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-ink-muted">{{ __('buyer.request_detail.part_name_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->part_name }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-ink-muted">{{ __('buyer.request_detail.reference_url_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">
                    @if ($partRequest->reference_url)
                        <a href="{{ $partRequest->reference_url }}" target="_blank" rel="noopener noreferrer" class="break-all text-brand-700 hover:underline">
                            {{ $partRequest->reference_url }}
                        </a>
                    @else
                        {{ __('buyer.request_detail.not_provided') }}
                    @endif
                </dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-ink-muted">{{ __('buyer.request_detail.memo_label') }}</dt>
                <dd class="mt-0.5 whitespace-pre-line font-medium text-ink">{{ $partRequest->memo ?? __('buyer.request_detail.not_provided') }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('buyer.request_detail.requested_at_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->created_at->format('Y-m-d H:i') }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <h2 class="text-base font-semibold text-ink">{{ __('buyer.request_detail.quote_section') }}</h2>

        @error('selectQuote')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror

        @if (count($options) > 0)
            <p class="mt-1 text-sm text-ink-muted">
                @if ($partRequest->hasBeenPaid())
                    {{ $partRequest->is_free ? __('buyer.request_detail.quote_locked_help_free') : __('buyer.request_detail.quote_locked_help') }}
                @else
                    {{ __('buyer.request_detail.quote_options_help') }}
                @endif
            </p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($options as $option)
                    <div class="rounded-md border p-4 {{ $option['is_selected'] ? 'border-brand-500 bg-brand-50' : 'border-line' }}">
                        <x-photo-gallery :photos="$option['photos']" />

                        <dl class="mt-3 space-y-2 text-sm">
                            <div>
                                <dt class="text-ink-muted">{{ __('buyer.request_detail.quote_quality_label') }}</dt>
                                <dd class="mt-0.5 font-medium text-ink">{{ __('enums.quality_rank.'.$option['quality_rank']->value) }}</dd>
                            </div>
                            <div>
                                <dt class="text-ink-muted">{{ __('buyer.request_detail.quote_price_label') }}</dt>
                                <dd class="mt-0.5 text-lg font-semibold text-ink">¥{{ number_format($option['buyer_price']) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if ($option['is_free'])
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                    {{ __('buyer.request_detail.quote_free_badge') }}
                                </span>
                            @endif

                            @if ($option['is_selected'])
                                <span class="inline-flex rounded-full bg-brand-100 px-2.5 py-1 text-xs font-medium text-brand-700">
                                    {{ __('buyer.request_detail.quote_selected_badge') }}
                                </span>
                            @elseif (! $partRequest->hasBeenPaid())
                                <button
                                    type="button"
                                    wire:click="selectQuote({{ $option['presented_quote_id'] }})"
                                    wire:confirm="{{ __('buyer.request_detail.select_quote_confirm', ['price' => number_format($option['buyer_price'])]) }}"
                                    wire:loading.attr="disabled"
                                    wire:target="selectQuote({{ $option['presented_quote_id'] }})"
                                    class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{ __('buyer.request_detail.select_quote_button') }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="mt-4 max-w-md text-xs text-ink-muted">{{ __('buyer.request_detail.quote_price_excludes_shipping') }}</p>

            @if ($partRequest->status === \App\Enums\RequestStatus::Quoted && collect($options)->contains('is_selected', true))
                <div class="mt-4">
                    <a
                        href="{{ route('buyer.requests.checkout', $partRequest->id) }}"
                        class="inline-flex rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                    >
                        {{ $partRequest->is_free ? __('buyer.request_detail.checkout_button_free') : __('buyer.request_detail.checkout_button') }}
                    </a>
                </div>
            @endif
        @else
            <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                {{ __('buyer.request_detail.awaiting_quote') }}
            </p>
        @endif
    </div>
</div>
