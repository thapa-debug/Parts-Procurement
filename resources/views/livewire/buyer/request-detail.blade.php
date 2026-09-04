<div class="max-w-3xl">
    <a href="{{ route('buyer.requests.index') }}" class="text-sm text-ink-muted hover:text-ink">
        &larr; {{ __('buyer.request_detail.back_link') }}
    </a>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-ink">{{ $partRequest->request_code }}</h1>
        <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
            {{ __('buyer.request_list.status.'.$partRequest->status->value) }}
        </span>
    </div>

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

        @if ($quote)
            <div class="mt-4 flex flex-wrap gap-6">
                @if ($quote['photos'] !== [])
                    <div class="flex flex-wrap gap-2">
                        @foreach ($quote['photos'] as $photoUrl)
                            <img src="{{ $photoUrl }}" class="h-24 w-24 rounded-md border border-line object-cover">
                        @endforeach
                    </div>
                @endif

                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-ink-muted">{{ __('buyer.request_detail.quote_quality_label') }}</dt>
                        <dd class="mt-0.5 font-medium text-ink">{{ __('enums.quality_rank.'.$quote['quality_rank']->value) }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">{{ __('buyer.request_detail.quote_price_label') }}</dt>
                        <dd class="mt-0.5 text-lg font-semibold text-ink">¥{{ number_format($partRequest->buyer_price) }}</dd>
                    </div>
                </dl>
            </div>
        @else
            <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                {{ __('buyer.request_detail.awaiting_quote') }}
            </p>
        @endif
    </div>
</div>
