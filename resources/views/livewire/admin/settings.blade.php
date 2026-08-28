<div class="max-w-2xl">
    <h1 class="text-2xl font-semibold text-ink">{{ __('admin.settings.heading') }}</h1>
    <p class="mt-1 text-sm text-ink-muted">{{ __('admin.settings.subheading') }}</p>

    <form wire:submit="save" class="mt-8 space-y-8">
        <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.settings.margin_section') }}</h2>

            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <label for="margin_rate" class="block text-sm font-medium text-ink">
                        {{ __('admin.settings.margin_rate_label') }} <x-required-mark />
                    </label>
                    <input
                        id="margin_rate"
                        type="number"
                        min="0"
                        wire:model="margin_rate"
                        placeholder="{{ __('admin.settings.margin_rate_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                    @error('margin_rate')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="margin_min_fee" class="block text-sm font-medium text-ink">
                        {{ __('admin.settings.margin_min_fee_label') }} <x-required-mark />
                    </label>
                    <input
                        id="margin_min_fee"
                        type="number"
                        min="0"
                        wire:model="margin_min_fee"
                        placeholder="{{ __('admin.settings.margin_min_fee_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                    @error('margin_min_fee')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <p class="mt-3 text-xs text-ink-muted">{{ __('admin.settings.margin_help') }}</p>
        </div>

        <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.settings.shipping_section') }}</h2>

            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <label for="shipping_fee_vehicle" class="block text-sm font-medium text-ink">
                        {{ __('admin.settings.shipping_fee_vehicle_label') }} <x-required-mark />
                    </label>
                    <input
                        id="shipping_fee_vehicle"
                        type="number"
                        min="0"
                        wire:model="shipping_fee_vehicle"
                        placeholder="{{ __('admin.settings.shipping_fee_vehicle_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                    @error('shipping_fee_vehicle')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="shipping_fee_container" class="block text-sm font-medium text-ink">
                        {{ __('admin.settings.shipping_fee_container_label') }} <x-required-mark />
                    </label>
                    <input
                        id="shipping_fee_container"
                        type="number"
                        min="0"
                        wire:model="shipping_fee_container"
                        placeholder="{{ __('admin.settings.shipping_fee_container_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                    @error('shipping_fee_container')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <p class="mt-3 text-xs text-ink-muted">{{ __('admin.settings.shipping_help') }}</p>
        </div>

        <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">{{ __('admin.settings.sender_section') }}</h2>

            <div class="mt-4">
                <label for="admin_sender_email" class="block text-sm font-medium text-ink">
                    {{ __('admin.settings.admin_sender_email_label') }} <x-required-mark />
                </label>
                <input
                    id="admin_sender_email"
                    type="email"
                    wire:model="admin_sender_email"
                    placeholder="{{ __('admin.settings.admin_sender_email_placeholder') }}"
                    class="mt-1.5 block w-full max-w-sm rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                @error('admin_sender_email')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs text-ink-muted">{{ __('admin.settings.admin_sender_email_help') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button
                type="submit"
                class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                {{ __('admin.settings.save_button') }}
            </button>

            @if ($justSaved)
                <span class="text-sm font-medium text-green-700">{{ __('admin.settings.saved') }}</span>
            @endif
        </div>
    </form>
</div>
