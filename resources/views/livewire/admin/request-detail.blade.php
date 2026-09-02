<div class="max-w-3xl">
    <a href="{{ route('admin.requests.index') }}" class="text-sm text-ink-muted hover:text-ink">
        &larr; {{ __('admin.request_detail.back_link') }}
    </a>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-ink">{{ $partRequest->request_code }}</h1>
        <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
            {{ __('admin.request_board.status.'.$partRequest->status->value) }}
        </span>
    </div>

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
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->maker }}</dd>
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

    @if ($justPresentedBuyerPrice !== null)
        <div class="mt-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700">
            {{ __('admin.request_detail.present_quote_confirmation', ['price' => number_format($justPresentedBuyerPrice)]) }}
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

    @if ($vendorResponses->isNotEmpty())
        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.request_detail.compare_section') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">
                {{ $partRequest->status === \App\Enums\RequestStatus::VendorInquiry
                    ? __('admin.request_detail.compare_help')
                    : __('admin.request_detail.compare_locked_help') }}
            </p>

            @error('presentQuote')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="mt-4 space-y-4">
                @foreach ($vendorResponses as $response)
                    @php $pricing = $vendorResponsePricing->get($response->id); @endphp
                    <div class="rounded-md border p-4 {{ $partRequest->selected_response_id === $response->id ? 'border-brand-500 bg-brand-50' : 'border-line' }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <span class="font-medium text-ink">{{ $response->vendor->company_name }}</span>
                                <span class="ml-2 text-xs text-ink-muted">{{ $response->vendor->contact_person }}</span>
                                @if ($partRequest->selected_response_id === $response->id)
                                    <span class="ml-2 inline-flex rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700">
                                        {{ __('admin.request_detail.presented_badge') }}
                                    </span>
                                @endif
                            </div>

                            @if ($response->is_no_stock)
                                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">
                                    {{ __('admin.request_detail.no_stock_badge') }}
                                </span>
                            @endif
                        </div>

                        @if (! $response->is_no_stock)
                            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                                <div>
                                    <dt class="text-ink-muted">{{ __('admin.request_detail.cost_price_column') }}</dt>
                                    <dd class="mt-0.5 font-mono font-medium text-ink">¥{{ number_format($response->cost_price) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ink-muted">{{ __('admin.request_detail.buyer_price_column') }}</dt>
                                    <dd class="mt-0.5 font-mono font-medium text-ink">
                                        {{ $pricing ? '¥'.number_format($pricing['buyer_price']) : __('admin.request_detail.not_provided') }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-ink-muted">{{ __('admin.request_detail.quality_rank_column') }}</dt>
                                    <dd class="mt-0.5 font-medium text-ink">{{ __('enums.quality_rank.'.$response->quality_rank->value) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ink-muted">{{ __('admin.request_detail.lead_time_column') }}</dt>
                                    <dd class="mt-0.5 font-medium text-ink">{{ __('enums.lead_time.'.$response->lead_time->value) }}</dd>
                                </div>
                            </dl>

                            @if ($response->comment)
                                <p class="mt-3 text-sm text-ink">{{ $response->comment }}</p>
                            @endif

                            @if ($response->photos->isNotEmpty())
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($response->photos as $photo)
                                        <a href="{{ $photo->url() }}" target="_blank" rel="noopener noreferrer">
                                            <img src="{{ $photo->url() }}" class="h-16 w-16 rounded-md border border-line object-cover">
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            @if ($partRequest->status === \App\Enums\RequestStatus::VendorInquiry)
                                <div class="mt-3">
                                    <button
                                        type="button"
                                        wire:click="presentQuote({{ $response->id }})"
                                        wire:confirm="{{ __('admin.request_detail.present_quote_confirm', ['price' => number_format($pricing['buyer_price'] ?? 0)]) }}"
                                        wire:loading.attr="disabled"
                                        wire:target="presentQuote({{ $response->id }})"
                                        class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {{ __('admin.request_detail.present_quote_button') }}
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
