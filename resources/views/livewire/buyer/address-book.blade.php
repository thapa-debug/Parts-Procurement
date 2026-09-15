<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-ink">{{ __('buyer.address_book.heading') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('buyer.address_book.subheading') }}</p>
        </div>

        @unless ($showForm)
            <button
                type="button"
                wire:click="startCreate"
                class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
            >
                {{ __('buyer.address_book.add_button') }}
            </button>
        @endunless
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-6 max-w-2xl space-y-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">
                {{ $editingAddressId ? __('buyer.address_book.edit_heading') : __('buyer.address_book.add_heading') }}
            </h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="recipient_name" class="block text-sm font-medium text-ink">
                        {{ __('buyer.address_book.recipient_name_label') }} <x-required-mark />
                    </label>
                    <input id="recipient_name" type="text" wire:model="recipient_name" class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('recipient_name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-ink">
                        {{ __('buyer.address_book.phone_label') }} <x-required-mark />
                    </label>
                    <input id="phone" type="text" wire:model="phone" class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('phone') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="country_id" class="block text-sm font-medium text-ink">
                        {{ __('buyer.address_book.country_label') }} <x-required-mark />
                    </label>
                    <select id="country_id" wire:model="country_id" class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <option value="">{{ __('buyer.address_book.country_placeholder_option') }}</option>
                        @foreach ($countryOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('country_id') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="postal_code" class="block text-sm font-medium text-ink">
                        {{ __('buyer.address_book.postal_code_label') }} <x-required-mark />
                    </label>
                    <input id="postal_code" type="text" wire:model="postal_code" class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('postal_code') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="state" class="block text-sm font-medium text-ink">{{ __('buyer.address_book.state_label') }}</label>
                    <input id="state" type="text" wire:model="state" class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('state') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="city" class="block text-sm font-medium text-ink">
                        {{ __('buyer.address_book.city_label') }} <x-required-mark />
                    </label>
                    <input id="city" type="text" wire:model="city" class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    @error('city') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="address_line1" class="block text-sm font-medium text-ink">
                    {{ __('buyer.address_book.address_line1_label') }} <x-required-mark />
                </label>
                <input id="address_line1" type="text" wire:model="address_line1" class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                @error('address_line1') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="address_line2" class="block text-sm font-medium text-ink">{{ __('buyer.address_book.address_line2_label') }}</label>
                <input id="address_line2" type="text" wire:model="address_line2" class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                @error('address_line2') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-ink">
                <input type="checkbox" wire:model="is_default" class="rounded border-line text-brand-600 focus:ring-brand-500">
                {{ __('buyer.address_book.is_default_label') }}
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ __('buyer.address_book.save_button') }}
                </button>
                <button type="button" wire:click="cancel" class="text-sm font-medium text-ink-muted hover:text-ink">
                    {{ __('buyer.address_book.cancel_button') }}
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6">
        @if ($addresses->isEmpty() && ! $showForm)
            <div class="rounded-lg border border-dashed border-line bg-surface p-8 text-center">
                <p class="text-sm text-ink-muted">{{ __('buyer.address_book.empty') }}</p>
                <button type="button" wire:click="startCreate" class="mt-3 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    {{ __('buyer.address_book.add_first_button') }}
                </button>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($addresses as $address)
                    <div wire:key="address-{{ $address->id }}" class="rounded-lg border p-4 shadow-sm {{ $address->is_default ? 'border-brand-500 bg-brand-50' : 'border-line bg-surface' }}">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-semibold text-ink">{{ $address->recipient_name }}</p>
                            @if ($address->is_default)
                                <span class="inline-flex shrink-0 rounded-full bg-brand-100 px-2.5 py-1 text-xs font-medium text-brand-700">
                                    {{ __('buyer.address_book.default_badge') }}
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 text-sm text-ink-muted">{{ $address->phone }}</p>
                        <p class="mt-2 text-sm text-ink">
                            {{ $address->postal_code }}<br>
                            {{ $address->country->name }}@if ($address->state), {{ $address->state }}@endif, {{ $address->city }}<br>
                            {{ $address->address_line1 }}
                            @if ($address->address_line2)
                                <br>{{ $address->address_line2 }}
                            @endif
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                            <button type="button" wire:click="startEdit({{ $address->id }})" class="font-medium text-brand-700 hover:text-brand-800">
                                {{ __('buyer.address_book.edit_button') }}
                            </button>
                            @unless ($address->is_default)
                                <button type="button" wire:click="setDefault({{ $address->id }})" class="font-medium text-brand-700 hover:text-brand-800">
                                    {{ __('buyer.address_book.set_default_button') }}
                                </button>
                            @endunless
                            <button
                                type="button"
                                wire:click="delete({{ $address->id }})"
                                wire:confirm="{{ __('buyer.address_book.delete_confirm') }}"
                                class="font-medium text-red-600 hover:text-red-700"
                            >
                                {{ __('buyer.address_book.delete_button') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
