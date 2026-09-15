<div class="max-w-3xl">
    <a href="{{ route('buyer.requests.show', $partRequest->id) }}" class="text-sm text-ink-muted hover:text-ink">
        &larr; {{ __('buyer.checkout.back_link') }}
    </a>

    <h1 class="mt-2 text-2xl font-semibold text-ink">{{ __('buyer.checkout.heading', ['code' => $partRequest->request_code]) }}</h1>

    @unless ($isEligible)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5">
            <p class="text-sm text-amber-800">{{ __('buyer.checkout.not_eligible') }}</p>
        </div>
    @else
        <form wire:submit="pay" class="mt-6 space-y-6">
            {{-- Shipping address --}}
            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h2 class="text-base font-semibold text-ink">{{ __('buyer.checkout.address_section') }}</h2>

                @if ($addresses->isEmpty() && ! $showNewAddressForm)
                    <p class="mt-3 text-sm text-ink-muted">{{ __('buyer.checkout.no_addresses') }}</p>
                @else
                    <div class="mt-4 space-y-2">
                        @foreach ($addresses as $address)
                            <label wire:key="address-{{ $address->id }}" class="flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm {{ (string) $selectedAddressId === (string) $address->id ? 'border-brand-500 bg-brand-50' : 'border-line' }}">
                                <input type="radio" wire:model="selectedAddressId" value="{{ $address->id }}" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                                <span>
                                    <span class="font-medium text-ink">{{ $address->recipient_name }}</span>
                                    @if ($address->is_default)
                                        <span class="ml-1 text-xs font-medium text-brand-700">({{ __('buyer.address_book.default_badge') }})</span>
                                    @endif
                                    <br>
                                    <span class="text-ink-muted">
                                        {{ $address->postal_code }}, {{ $address->country->name }}@if ($address->state), {{ $address->state }}@endif, {{ $address->city }}, {{ $address->address_line1 }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('selectedAddressId') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                <button type="button" wire:click="toggleNewAddressForm" class="mt-4 text-sm font-medium text-brand-700 hover:text-brand-800">
                    {{ $showNewAddressForm ? __('buyer.checkout.cancel_new_address_button') : __('buyer.checkout.add_new_address_button') }}
                </button>

                @if ($showNewAddressForm)
                    <div class="mt-4 space-y-4 rounded-md border border-line bg-surface-muted p-4">
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

                        <button
                            type="button"
                            wire:click="addAddress"
                            wire:loading.attr="disabled"
                            wire:target="addAddress"
                            class="rounded-md bg-ink px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-ink/90 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ __('buyer.checkout.save_new_address_button') }}
                        </button>
                    </div>
                @endif
            </div>

            {{-- Shipping method --}}
            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h2 class="text-base font-semibold text-ink">{{ __('buyer.checkout.method_section') }}</h2>

                <div class="mt-4 space-y-2">
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-md border p-3 text-sm {{ $shippingMethod === 'vehicle' ? 'border-brand-500 bg-brand-50' : 'border-line' }}">
                        <span class="flex items-center gap-3">
                            <input type="radio" wire:model="shippingMethod" value="vehicle" class="text-brand-600 focus:ring-brand-500">
                            <span class="font-medium text-ink">{{ __('buyer.checkout.method.vehicle') }}</span>
                        </span>
                        <span class="font-mono text-ink">¥{{ number_format($shippingFees['vehicle']) }}</span>
                    </label>

                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-md border p-3 text-sm {{ $shippingMethod === 'container' ? 'border-brand-500 bg-brand-50' : 'border-line' }}">
                        <span class="flex items-center gap-3">
                            <input type="radio" wire:model="shippingMethod" value="container" class="text-brand-600 focus:ring-brand-500">
                            <span class="font-medium text-ink">{{ __('buyer.checkout.method.container') }}</span>
                        </span>
                        <span class="font-mono text-ink">¥{{ number_format($shippingFees['container']) }}</span>
                    </label>
                </div>
                @error('shippingMethod') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                <p class="mt-3 text-xs text-ink-muted">{{ __('buyer.checkout.dhl_note') }}</p>
            </div>

            {{-- Fee breakdown --}}
            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h2 class="text-base font-semibold text-ink">{{ __('buyer.checkout.summary_section') }}</h2>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-muted">{{ __('buyer.checkout.summary_part_price') }}</dt>
                        <dd class="font-mono text-ink">¥{{ number_format($partRequest->buyer_price) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-muted">{{ __('buyer.checkout.summary_shipping_fee') }}</dt>
                        <dd class="font-mono text-ink">
                            ¥{{ number_format($shippingMethod ? ($shippingFees[$shippingMethod] ?? 0) : 0) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between border-t border-line pt-2 text-base font-semibold">
                        <dt class="text-ink">{{ __('buyer.checkout.summary_total') }}</dt>
                        <dd class="font-mono text-ink">
                            ¥{{ number_format($partRequest->buyer_price + ($shippingMethod ? ($shippingFees[$shippingMethod] ?? 0) : 0)) }}
                        </dd>
                    </div>
                </dl>
            </div>

            @error('pay') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="pay"
                class="w-full rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ __('buyer.checkout.pay_button') }}
            </button>
        </form>
    @endunless
</div>
