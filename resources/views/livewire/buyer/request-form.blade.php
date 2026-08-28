<div>
    <div>
        <h1 class="text-2xl font-semibold text-ink">{{ __('buyer.request_form.heading') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('buyer.request_form.subheading') }}</p>
    </div>

    @if ($submittedCode)
        <div class="mt-6 max-w-2xl rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700">
            {{ __('buyer.request_form.submitted', ['code' => $submittedCode]) }}
        </div>
    @endif

    @if ($blockedReason)
        <div class="mt-6 max-w-2xl rounded-lg border border-amber-200 bg-amber-50 p-5">
            <h2 class="text-sm font-semibold text-amber-800">
                {{ __('buyer.request_form.blocked.'.$blockedReason.'_heading') }}
            </h2>
            <p class="mt-1 text-sm text-amber-700">
                {{ __('buyer.request_form.blocked.'.$blockedReason.'_body') }}
            </p>
        </div>
    @else
        <form wire:submit="submit" class="mt-6 max-w-2xl space-y-5">
            <div>
                <label class="block text-sm font-medium text-ink">
                    {{ __('buyer.request_form.part_type_label') }} <x-required-mark />
                </label>
                <div class="mt-1.5 flex flex-wrap gap-3">
                    @foreach (\App\Enums\PartType::cases() as $option)
                        <label class="flex items-center gap-2 rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" wire:model="part_type" value="{{ $option->value }}" class="text-brand-600 focus:ring-1 focus:ring-brand-500">
                            {{ __('buyer.request_form.part_type.'.$option->value) }}
                        </label>
                    @endforeach
                </div>
                @error('part_type')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="maker" class="block text-sm font-medium text-ink">
                        {{ __('buyer.request_form.maker_label') }} <x-required-mark />
                    </label>
                    <select
                        id="maker"
                        wire:model="maker"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                        <option value="">{{ __('buyer.request_form.maker_placeholder_option') }}</option>
                        @foreach (__('buyer.request_form.maker_options') as $makerOption)
                            <option value="{{ $makerOption }}">{{ $makerOption }}</option>
                        @endforeach
                    </select>
                    @error('maker')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="car_model" class="block text-sm font-medium text-ink">
                        {{ __('buyer.request_form.car_model_label') }} <x-required-mark />
                    </label>
                    <input
                        id="car_model"
                        type="text"
                        wire:model="car_model"
                        placeholder="{{ __('buyer.request_form.car_model_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                    @error('car_model')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="vin" class="block text-sm font-medium text-ink">
                        {{ __('buyer.request_form.vin_label') }}
                    </label>
                    <input
                        id="vin"
                        type="text"
                        wire:model="vin"
                        placeholder="{{ __('buyer.request_form.vin_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                    <p class="mt-1 text-xs text-ink-muted">{{ __('buyer.request_form.vin_help') }}</p>
                    @error('vin')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="mfg_date" class="block text-sm font-medium text-ink">
                        {{ __('buyer.request_form.mfg_date_label') }}
                    </label>
                    <input
                        id="mfg_date"
                        type="text"
                        wire:model="mfg_date"
                        placeholder="{{ __('buyer.request_form.mfg_date_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                    <p class="mt-1 text-xs text-ink-muted">{{ __('buyer.request_form.mfg_date_help') }}</p>
                    @error('mfg_date')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="oem_part_number" class="block text-sm font-medium text-ink">
                    {{ __('buyer.request_form.oem_part_number_label') }}
                </label>
                <input
                    id="oem_part_number"
                    type="text"
                    wire:model="oem_part_number"
                    placeholder="{{ __('buyer.request_form.oem_part_number_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                @error('oem_part_number')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="part_name" class="block text-sm font-medium text-ink">
                    {{ __('buyer.request_form.part_name_label') }} <x-required-mark />
                </label>
                <input
                    id="part_name"
                    type="text"
                    wire:model="part_name"
                    placeholder="{{ __('buyer.request_form.part_name_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                @error('part_name')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="reference_url" class="block text-sm font-medium text-ink">
                    {{ __('buyer.request_form.reference_url_label') }}
                </label>
                <input
                    id="reference_url"
                    type="url"
                    wire:model="reference_url"
                    placeholder="{{ __('buyer.request_form.reference_url_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 font-mono text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                <p class="mt-1 text-xs text-ink-muted">{{ __('buyer.request_form.reference_url_help') }}</p>
                @error('reference_url')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="memo" class="block text-sm font-medium text-ink">
                    {{ __('buyer.request_form.memo_label') }}
                </label>
                <textarea
                    id="memo"
                    rows="3"
                    wire:model="memo"
                    placeholder="{{ __('buyer.request_form.memo_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                ></textarea>
                @error('memo')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <button
                    type="submit"
                    class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                >
                    {{ __('buyer.request_form.submit') }}
                </button>
            </div>
        </form>
    @endif
</div>
