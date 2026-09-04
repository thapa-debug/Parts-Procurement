<div class="max-w-3xl">
    <a href="{{ route('vendor.inbox') }}" class="text-sm text-ink-muted hover:text-ink">
        &larr; {{ __('vendor.request_response.back_link') }}
    </a>

    <h1 class="mt-2 text-2xl font-semibold text-ink">{{ $partRequest->request_code }}</h1>

    <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <h2 class="text-base font-semibold text-ink">{{ __('vendor.request_response.details_section') }}</h2>

        <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-ink-muted">{{ __('vendor.request_response.part_type_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ __('vendor.inbox.part_type.'.$partRequest->part_type->value) }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('vendor.request_response.maker_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->maker->name }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('vendor.request_response.car_model_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->car_model }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('vendor.request_response.vin_label') }}</dt>
                <dd class="mt-0.5 font-mono font-medium text-ink">{{ $partRequest->vin }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('vendor.request_response.oem_part_number_label') }}</dt>
                <dd class="mt-0.5 font-mono font-medium text-ink">{{ $partRequest->oem_part_number ?? __('vendor.request_response.not_provided') }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-ink-muted">{{ __('vendor.request_response.part_name_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->part_name }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-ink-muted">{{ __('vendor.request_response.reference_url_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">
                    @if ($partRequest->reference_url)
                        <a href="{{ $partRequest->reference_url }}" target="_blank" rel="noopener noreferrer" class="break-all text-brand-700 hover:underline">
                            {{ $partRequest->reference_url }}
                        </a>
                    @else
                        {{ __('vendor.request_response.not_provided') }}
                    @endif
                </dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-ink-muted">{{ __('vendor.request_response.memo_label') }}</dt>
                <dd class="mt-0.5 whitespace-pre-line font-medium text-ink">{{ $partRequest->memo ?? __('vendor.request_response.not_provided') }}</dd>
            </div>

            <div>
                <dt class="text-ink-muted">{{ __('vendor.request_response.requested_at_label') }}</dt>
                <dd class="mt-0.5 font-medium text-ink">{{ $partRequest->created_at->format('Y-m-d H:i') }}</dd>
            </div>
        </dl>
    </div>

    @if ($blockedReason)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5">
            <h2 class="text-sm font-semibold text-amber-800">
                {{ __('vendor.request_response.blocked.'.$blockedReason.'_heading') }}
            </h2>
            <p class="mt-1 text-sm text-amber-700">
                {{ __('vendor.request_response.blocked.'.$blockedReason.'_body') }}
            </p>
        </div>
    @elseif ($myResponse)
        <div class="mt-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700">
            <h2 class="font-semibold">{{ __('vendor.request_response.submitted_section') }}</h2>
            <p class="mt-1">
                @if ($myResponse->is_no_stock)
                    {{ __('vendor.request_response.submitted_no_stock') }}
                @else
                    {{ __('vendor.request_response.submitted_quote', [
                        'price' => number_format($myResponse->cost_price),
                        'rank' => strtoupper($myResponse->quality_rank->value),
                        'lead_time' => __('enums.lead_time.'.$myResponse->lead_time->value),
                    ]) }}
                @endif
            </p>
        </div>
    @else
        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('vendor.request_response.response_section') }}</h2>

            <form wire:submit="sendResponse" class="mt-4 space-y-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="cost_price" class="block text-sm font-medium text-ink">
                            {{ __('vendor.request_response.cost_price_label') }} <x-required-mark />
                        </label>
                        <input
                            id="cost_price"
                            type="number"
                            wire:model="cost_price"
                            placeholder="{{ __('vendor.request_response.cost_price_placeholder') }}"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        @error('cost_price')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="quality_rank" class="block text-sm font-medium text-ink">
                            {{ __('vendor.request_response.quality_rank_label') }} <x-required-mark />
                        </label>
                        <select
                            id="quality_rank"
                            wire:model="quality_rank"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                            <option value=""></option>
                            @foreach (\App\Enums\QualityRank::cases() as $option)
                                <option value="{{ $option->value }}">{{ __('enums.quality_rank.'.$option->value) }}</option>
                            @endforeach
                        </select>
                        @error('quality_rank')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="lead_time" class="block text-sm font-medium text-ink">
                        {{ __('vendor.request_response.lead_time_label') }} <x-required-mark />
                    </label>
                    <select
                        id="lead_time"
                        wire:model="lead_time"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                        <option value=""></option>
                        @foreach (\App\Enums\LeadTime::cases() as $option)
                            <option value="{{ $option->value }}">{{ __('enums.lead_time.'.$option->value) }}</option>
                        @endforeach
                    </select>
                    @error('lead_time')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div
                    x-data="{
                        uploading: false,
                        uploadedCount: 0,
                        totalCount: 0,
                        async selectPhotos(event) {
                            const files = Array.from(event.target.files);
                            event.target.value = '';
                            if (files.length === 0) return;

                            this.uploading = true;
                            this.uploadedCount = 0;
                            this.totalCount = files.length;

                            // One $wire.$upload() call per file, awaited in
                            // sequence -- never $wire.$uploadMultiple(), which
                            // Livewire's S3 driver rejects outright. Each call
                            // is a genuine single-file upload
                            // (UploadManager.upload() always sets
                            // `multiple: false`), so this stays S3-safe no
                            // matter how many files the native dialog let the
                            // vendor pick at once. Sequential, not
                            // concurrent, to avoid overlapping requests
                            // against the local dev server's single-threaded
                            // built-in PHP server.
                            for (const file of files) {
                                await new Promise((resolve) => {
                                    $wire.$upload('photos', file,
                                        () => { this.uploadedCount++; resolve(); },
                                        () => { this.uploadedCount++; resolve(); },
                                    );
                                });
                            }

                            this.uploading = false;
                        },
                    }"
                >
                    <label for="photos" class="block text-sm font-medium text-ink">
                        {{ __('vendor.request_response.photos_label') }} <x-required-mark />
                    </label>

                    @if (count($photos) < $maxPhotos)
                        <input
                            id="photos"
                            type="file"
                            multiple
                            accept="image/*"
                            x-on:change="selectPhotos($event)"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-700"
                        >
                    @endif

                    {{-- Two plain elements toggled with x-show (visibility
                         only), not x-if/<template> (which clones/destroys
                         DOM content) -- the count/max text below is
                         server-rendered on every Livewire update exactly
                         like the thumbnails are, so it must stay a normal,
                         continuously-present element for Livewire's morph
                         to keep it in sync. A <template x-if> block instead
                         left it showing whatever was baked in the one time
                         Alpine last cloned it, which could go stale. --}}
                    <p class="mt-1 text-xs text-ink-muted" x-show="uploading" x-cloak>
                        {{ __('vendor.request_response.uploading') }} (<span x-text="uploadedCount"></span>/<span x-text="totalCount"></span>)
                    </p>
                    <p class="mt-1 text-xs text-ink-muted" x-show="!uploading">
                        {{ __('vendor.request_response.photos_help', ['count' => count($photos), 'max' => $maxPhotos]) }}
                    </p>

                    @error('photos.*')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('photos')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    @if (count($photos) > 0)
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($photos as $index => $photo)
                                <div class="relative">
                                    <img src="{{ $photo->temporaryUrl() }}" class="h-16 w-16 rounded-md border border-line object-cover">
                                    <button
                                        type="button"
                                        wire:click="removePhoto({{ $index }})"
                                        aria-label="{{ __('vendor.request_response.remove_photo') }}"
                                        class="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white shadow"
                                    >&times;</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <label for="comment" class="block text-sm font-medium text-ink">
                        {{ __('vendor.request_response.comment_label') }} <x-required-mark />
                    </label>
                    <textarea
                        id="comment"
                        rows="3"
                        wire:model="comment"
                        placeholder="{{ __('vendor.request_response.comment_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    ></textarea>
                    @error('comment')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                        wire:loading.attr="disabled"
                        wire:target="sendResponse"
                    >
                        {{ __('vendor.request_response.submit_button') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('vendor.request_response.no_stock_section') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ __('vendor.request_response.no_stock_help') }}</p>

            <div class="mt-4">
                <button
                    type="button"
                    wire:click="sendNoStock"
                    wire:confirm="{{ __('vendor.request_response.no_stock_confirm') }}"
                    wire:loading.attr="disabled"
                    wire:target="sendNoStock"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ __('vendor.request_response.no_stock_button') }}
                </button>
            </div>
        </div>
    @endif
</div>
