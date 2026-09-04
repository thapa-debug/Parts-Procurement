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

    <div class="mt-8 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <h2 class="text-base font-semibold text-ink">{{ __('admin.settings.countries_section') }}</h2>
        <p class="mt-1 text-xs text-ink-muted">{{ __('admin.settings.countries_help') }}</p>

        <div class="mt-4 flex items-end gap-3">
            <div class="flex-1">
                <label for="new_country_name" class="block text-sm font-medium text-ink">
                    {{ __('admin.settings.country_name_label') }} <x-required-mark />
                </label>
                <input
                    id="new_country_name"
                    type="text"
                    wire:model="new_country_name"
                    wire:keydown.enter.prevent="addCountry"
                    placeholder="{{ __('admin.settings.country_name_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
            </div>
            <button
                type="button"
                wire:click="addCountry"
                wire:loading.attr="disabled"
                wire:target="addCountry"
                class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ __('admin.settings.add_country_button') }}
            </button>
        </div>
        @error('new_country_name')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <div class="mt-4">
            <input
                type="search"
                wire:model.live.debounce.300ms="countrySearch"
                placeholder="{{ __('admin.settings.country_search_placeholder') }}"
                class="w-full max-w-sm rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
            >
        </div>

        <div class="mt-4 max-h-80 overflow-y-auto overflow-x-auto rounded-md border border-line">
            <table class="min-w-full divide-y divide-line text-sm">
                <thead class="sticky top-0 bg-surface-muted">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-ink-muted">
                        <th class="px-4 py-2.5">{{ __('admin.settings.country_table.name') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.settings.country_table.status') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.settings.country_table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($countries as $country)
                        <tr wire:key="country-{{ $country->id }}" class="{{ $country->is_active ? '' : 'opacity-60' }}">
                            @if ($editingCountryId === $country->id)
                                <td class="px-4 py-2.5" colspan="2">
                                    <input
                                        type="text"
                                        wire:model="editing_country_name"
                                        wire:keydown.enter.prevent="saveCountry"
                                        class="block w-full rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                                    >
                                    @error('editing_country_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <button type="button" wire:click="saveCountry" class="text-sm font-medium text-brand-700 hover:text-brand-800">
                                        {{ __('admin.settings.save_country_button') }}
                                    </button>
                                    <button type="button" wire:click="cancelEditingCountry" class="ml-3 text-sm font-medium text-ink-muted hover:text-ink">
                                        {{ __('admin.settings.cancel_button') }}
                                    </button>
                                </td>
                            @else
                                <td class="px-4 py-2.5 font-medium text-ink">{{ $country->name }}</td>
                                <td class="px-4 py-2.5">
                                    @if ($country->is_active)
                                        <span class="inline-flex rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                                            {{ __('admin.settings.country_status.active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-surface-muted px-2.5 py-1 text-xs font-medium text-ink-muted">
                                            {{ __('admin.settings.country_status.inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <button type="button" wire:click="startEditingCountry({{ $country->id }})" class="text-sm font-medium text-brand-700 hover:text-brand-800">
                                        {{ __('admin.settings.edit_country_button') }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="toggleCountryActive({{ $country->id }})"
                                        class="ml-3 text-sm font-medium {{ $country->is_active ? 'text-red-600 hover:text-red-700' : 'text-brand-700 hover:text-brand-800' }}"
                                    >
                                        {{ $country->is_active ? __('admin.settings.deactivate_country_button') : __('admin.settings.activate_country_button') }}
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-ink-muted">
                                {{ __('admin.settings.country_empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <h2 class="text-base font-semibold text-ink">{{ __('admin.settings.makers_section') }}</h2>
        <p class="mt-1 text-xs text-ink-muted">{{ __('admin.settings.makers_help') }}</p>

        <div class="mt-4 flex items-end gap-3">
            <div class="flex-1">
                <label for="new_maker_name" class="block text-sm font-medium text-ink">
                    {{ __('admin.settings.maker_name_label') }} <x-required-mark />
                </label>
                <input
                    id="new_maker_name"
                    type="text"
                    wire:model="new_maker_name"
                    wire:keydown.enter.prevent="addMaker"
                    placeholder="{{ __('admin.settings.maker_name_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
            </div>
            <button
                type="button"
                wire:click="addMaker"
                wire:loading.attr="disabled"
                wire:target="addMaker"
                class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ __('admin.settings.add_maker_button') }}
            </button>
        </div>
        @error('new_maker_name')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <div class="mt-4">
            <input
                type="search"
                wire:model.live.debounce.300ms="makerSearch"
                placeholder="{{ __('admin.settings.maker_search_placeholder') }}"
                class="w-full max-w-sm rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
            >
        </div>

        <div class="mt-4 max-h-80 overflow-y-auto overflow-x-auto rounded-md border border-line">
            <table class="min-w-full divide-y divide-line text-sm">
                <thead class="sticky top-0 bg-surface-muted">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-ink-muted">
                        <th class="px-4 py-2.5">{{ __('admin.settings.maker_table.name') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.settings.maker_table.status') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.settings.maker_table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($makers as $maker)
                        <tr wire:key="maker-{{ $maker->id }}" class="{{ $maker->is_active ? '' : 'opacity-60' }}">
                            @if ($editingMakerId === $maker->id)
                                <td class="px-4 py-2.5" colspan="2">
                                    <input
                                        type="text"
                                        wire:model="editing_maker_name"
                                        wire:keydown.enter.prevent="saveMaker"
                                        class="block w-full rounded-md border border-line bg-surface px-2 py-1 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                                    >
                                    @error('editing_maker_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <button type="button" wire:click="saveMaker" class="text-sm font-medium text-brand-700 hover:text-brand-800">
                                        {{ __('admin.settings.save_maker_button') }}
                                    </button>
                                    <button type="button" wire:click="cancelEditingMaker" class="ml-3 text-sm font-medium text-ink-muted hover:text-ink">
                                        {{ __('admin.settings.cancel_button') }}
                                    </button>
                                </td>
                            @else
                                <td class="px-4 py-2.5 font-medium text-ink">{{ $maker->name }}</td>
                                <td class="px-4 py-2.5">
                                    @if ($maker->is_active)
                                        <span class="inline-flex rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                                            {{ __('admin.settings.maker_status.active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-surface-muted px-2.5 py-1 text-xs font-medium text-ink-muted">
                                            {{ __('admin.settings.maker_status.inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <button type="button" wire:click="startEditingMaker({{ $maker->id }})" class="text-sm font-medium text-brand-700 hover:text-brand-800">
                                        {{ __('admin.settings.edit_maker_button') }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="toggleMakerActive({{ $maker->id }})"
                                        class="ml-3 text-sm font-medium {{ $maker->is_active ? 'text-red-600 hover:text-red-700' : 'text-brand-700 hover:text-brand-800' }}"
                                    >
                                        {{ $maker->is_active ? __('admin.settings.deactivate_maker_button') : __('admin.settings.activate_maker_button') }}
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-ink-muted">
                                {{ __('admin.settings.maker_empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
