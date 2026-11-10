<div class="max-w-3xl">
    {{-- Stripe.js MUST be loaded from Stripe's own CDN, never self-hosted/
    bundled (PCI SAQ-A requirement) -- only when it's actually the active
    gateway and there's a real charge to make (無償/free orders never load
    it, same as the stub gateway). Livewire full-page components need a
    single root element, so this stays inside the wrapping <div> rather
    than as a sibling of it. --}}
    @if ($isStripeGateway && ! $partRequest->is_free)
        <script src="https://js.stripe.com/v3/"></script>
    @endif

    <a href="{{ route('buyer.requests.show', $partRequest->id) }}" class="text-sm text-ink-muted hover:text-ink">
        &larr; {{ __('buyer.checkout.back_link') }}
    </a>

    <h1 class="mt-2 text-2xl font-semibold text-ink">{{ __('buyer.checkout.heading', ['code' => $partRequest->request_code]) }}</h1>

    @if ($canResumePayment)
        {{-- Abandoned checkout, resumed on the SAME PaymentIntent
        (Checkout::resumePayment / ResumeStripePaymentAction). Same Alpine
        component as the normal card step, in resume mode: it fetches the
        existing intent's client secret on load and mounts the card form
        against it -- never a new intent. The address is shown read-only:
        it was snapshotted when checkout started. --}}
        <form
            x-data="stripeCheckoutForm({
                publishableKey: @js($stripePublishableKey),
                resume: true,
            })"
            x-init="init()"
            @submit.prevent="cardMounted ? submit() : proceedToPayment()"
            class="mt-6 space-y-6"
        >
            <div class="rounded-lg border border-sky-200 bg-sky-50 p-4">
                <p class="text-sm text-sky-800">{{ __('buyer.checkout.resume_help') }}</p>
            </div>

            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h2 class="text-base font-semibold text-ink">{{ __('buyer.checkout.resume_shipping_to') }}</h2>
                <p class="mt-2 text-sm text-ink">
                    <span class="font-medium">{{ $partRequest->shipping_recipient_name }}</span><br>
                    <span class="text-ink-muted">
                        {{ $partRequest->shipping_postal_code }}, {{ $partRequest->shipping_country }}@if ($partRequest->shipping_state), {{ $partRequest->shipping_state }}@endif, {{ $partRequest->shipping_city }}, {{ $partRequest->shipping_address_line1 }}@if ($partRequest->shipping_address_line2), {{ $partRequest->shipping_address_line2 }}@endif
                    </span>
                </p>
            </div>

            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h2 class="text-base font-semibold text-ink">{{ __('buyer.checkout.summary_section') }}</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-muted">{{ __('buyer.checkout.summary_part_price') }}</dt>
                        <dd class="font-mono text-ink">¥{{ number_format($partRequest->buyer_price) }}</dd>
                    </div>
                    {{-- Read-only: the method and its fee were snapshotted
                    when checkout started, and the intent was created for
                    exactly this total. --}}
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-muted">
                            {{ __('buyer.checkout.summary_shipping_fee') }}
                            @if ($partRequest->shipping_method)
                                <span class="text-xs">({{ __('enums.shipping_method.'.$partRequest->shipping_method->value) }})</span>
                            @endif
                        </dt>
                        <dd class="font-mono text-ink">¥{{ number_format($partRequest->shipping_fee) }}</dd>
                    </div>
                    <div class="flex items-center justify-between border-t border-line pt-2 text-base font-semibold">
                        <dt class="text-ink">{{ __('buyer.checkout.summary_total') }}</dt>
                        <dd class="font-mono text-ink">¥{{ number_format($partRequest->buyer_price + $partRequest->shipping_fee) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h2 class="text-base font-semibold text-ink">{{ __('buyer.checkout.card_section') }}</h2>
                <p x-show="!cardMounted && !errorMessage" class="mt-2 text-sm text-ink-muted">{{ __('buyer.checkout.resume_loading') }}</p>
                <div x-show="cardMounted">
                    <p class="mt-1 text-xs text-ink-muted">{{ __('buyer.checkout.card_section_help') }}</p>
                    <div x-ref="paymentElement" class="mt-4"></div>
                </div>
                <p x-show="errorMessage" x-cloak x-text="errorMessage" class="mt-2 text-sm text-red-600" role="alert"></p>
            </div>

            <button
                type="submit"
                x-show="cardMounted"
                :disabled="processing"
                x-text="processing ? @js(__('buyer.checkout.processing_button')) : @js(__('buyer.checkout.pay_button'))"
                class="w-full rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
            >{{ __('buyer.checkout.pay_button') }}</button>
        </form>
    @elseif (! $isEligible)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5">
            <p class="text-sm text-amber-800">
                {{ $partRequest->status === \App\Enums\RequestStatus::AwaitingPayment
                    ? __('buyer.checkout.awaiting_payment')
                    : __('buyer.checkout.not_eligible') }}
            </p>
        </div>
    @else
        {{-- The Alpine/Stripe.js-driven form only takes over once an
        address is actually selected -- pre-selected automatically for any
        buyer who already has one (Checkout::mount()), so this only
        affects a buyer with zero saved addresses. Until then this stays
        the plain wire:submit form: nothing Stripe-related exists yet (see
        the Card details section below), so a normal validation re-render
        is completely safe, and pay() itself reports the "select an
        address" error the classic way in that state (see its own
        $protectCardForm docblock). This is what keeps the buyer from
        ever reaching the card step without an address already
        chosen -- rather than filling in card details first and only
        being told afterward. --}}
        <form
            @if ($cardStepReady)
                x-data="stripeCheckoutForm({
                    publishableKey: @js($stripePublishableKey),
                })"
                x-init="init()"
                @submit.prevent="cardMounted ? submit() : proceedToPayment()"
            @else
                {{-- pay() is handed the total the buyer is looking at
                (re-rendered on every shipping-method change), so it can
                refuse rather than charge anything else. --}}
                wire:submit="{{ $partRequest->is_free ? 'confirmFree' : 'pay('.($checkoutTotal ?? 'null').')' }}"
            @endif
            class="mt-6 space-y-6"
        >
            {{-- Shipping address --}}
            <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                <h2 class="text-base font-semibold text-ink">{{ __('buyer.checkout.address_section') }}</h2>

                @if ($addresses->isEmpty() && ! $showNewAddressForm)
                    <p class="mt-3 text-sm text-ink-muted">{{ __('buyer.checkout.no_addresses') }}</p>
                @else
                    <div class="mt-4 space-y-2">
                        @foreach ($addresses as $address)
                            {{-- Highlighting is pure CSS (has-checked:),
                            not a PHP-computed class -- it must update the
                            instant the buyer clicks a different radio, with
                            no network round trip. Once the card step is
                            reached ($cardStepReady), switching addresses
                            uses a *deferred* wire:model precisely so it
                            doesn't cause one: any round trip re-renders the
                            component, and once the Payment Element is
                            actually mounted, Livewire's morph would reset
                            its mount <div> back to its empty, server-
                            rendered state, wiping the mounted Stripe form
                            out from under the buyer (the same bug class as
                            pay()'s own $protectCardForm guard). The buyer's
                            eventual choice still reaches the server
                            correctly -- $wire.proceedToPayment() syncs
                            whatever's deferred along with the method call
                            itself. Before the card step is reached (no
                            address chosen yet), .live is exactly what's
                            needed instead: selecting an address must
                            trigger an immediate re-render, since that's
                            what reveals the card step/lifts the "please
                            select an address" block in the first place. --}}
                            <label wire:key="address-{{ $address->id }}" class="flex cursor-pointer items-start gap-3 rounded-md border border-line p-3 text-sm has-checked:border-brand-500 has-checked:bg-brand-50 has-disabled:cursor-not-allowed has-disabled:opacity-60">
                                {{-- name= is required for the browser's own
                                native radio-group exclusivity: previously
                                this relied on Livewire's live round trip to
                                re-render every radio's checked state from
                                the server on every click, which masked the
                                missing attribute. A deferred wire:model
                                (above) skips that round trip entirely, so
                                the browser itself must be the one enforcing
                                "only one checked at a time" now.
                                :disabled="cardMounted" -- once the real
                                PaymentIntent has been created
                                (proceedToPayment()), the shipping address
                                is already permanently snapshotted onto the
                                request (CheckoutAction's own
                                SnapshotShippingAddressAction, inside the
                                same DB transaction as the charge) -- so
                                switching the radio at that point would look
                                like it worked but silently do nothing.
                                Disabling it is the honest UI. --}}
                                <input type="radio" name="selectedAddressId" wire:model{{ $cardStepReady ? '' : '.live' }}="selectedAddressId" value="{{ $address->id }}" class="mt-0.5 text-brand-600 focus:ring-brand-500" @if ($cardStepReady) :disabled="cardMounted" @endif>
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

            @if ($partRequest->is_free)
                {{-- 無償 (free) flow (CLAUDE.md §14 Phase 4 slice 5): no fee
                breakdown, no payment section -- there's nothing to pay. --}}
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-sm text-emerald-800">{{ __('buyer.checkout.free_order_note') }}</p>
                </div>
            @else
                {{-- Shipping method -- chosen before "Proceed to payment",
                because the PaymentIntent is created for the total that
                includes this fee. wire:model.live: each change re-renders
                the fee and total from the server (ShippingFeeService),
                safe because nothing is mounted yet. Once the card form is
                mounted (cardMounted) the method is already snapshotted
                and the radios are disabled -- the same lock as the
                address radios above. --}}
                <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-ink">{{ __('buyer.checkout.shipping_method_section') }}</h2>

                    <div class="mt-4 space-y-2">
                        @foreach (\App\Enums\ShippingMethod::cases() as $method)
                            @php($fee = $shippingFees[$method->value] ?? null)
                            <label wire:key="shipping-method-{{ $method->value }}" class="flex cursor-pointer items-start justify-between gap-3 rounded-md border border-line p-3 text-sm has-checked:border-brand-500 has-checked:bg-brand-50 has-disabled:cursor-not-allowed has-disabled:opacity-60">
                                <span class="flex items-start gap-3">
                                    <input
                                        type="radio"
                                        name="shippingMethod"
                                        wire:model.live="shippingMethod"
                                        value="{{ $method->value }}"
                                        class="mt-0.5 text-brand-600 focus:ring-brand-500"
                                        @if ($cardStepReady) :disabled="cardMounted || @js($fee === null)" @elseif ($fee === null) disabled @endif
                                    >
                                    <span>
                                        <span class="font-medium text-ink">{{ __('enums.shipping_method.'.$method->value) }}</span>
                                        <br>
                                        <span class="text-ink-muted">{{ __('buyer.checkout.shipping_method_help.'.$method->value) }}</span>
                                    </span>
                                </span>
                                <span class="shrink-0 font-mono text-ink">
                                    {{ $fee === null ? __('buyer.checkout.shipping_fee_needs_address') : '¥'.number_format($fee) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('shippingMethod') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Fee breakdown -- always the server's figures for the
                current choice. data-checkout-total is what the card flow
                sends to pay() as the total the buyer saw. --}}
                <div class="rounded-lg border border-line bg-surface p-6 shadow-sm" wire:loading.class="opacity-60" wire:target="shippingMethod">
                    <h2 class="text-base font-semibold text-ink">{{ __('buyer.checkout.summary_section') }}</h2>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-muted">{{ __('buyer.checkout.summary_part_price') }}</dt>
                            <dd class="font-mono text-ink">¥{{ number_format($partRequest->buyer_price) }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-muted">
                                {{ __('buyer.checkout.summary_shipping_fee') }}
                                <span class="text-xs">({{ __('enums.shipping_method.'.$shippingMethod) }})</span>
                            </dt>
                            <dd class="font-mono text-ink">{{ $chosenShippingFee === null ? '—' : '¥'.number_format($chosenShippingFee) }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-line pt-2 text-base font-semibold">
                            <dt class="text-ink">{{ __('buyer.checkout.summary_total') }}</dt>
                            <dd class="font-mono text-ink" data-checkout-total="{{ $checkoutTotal }}">{{ $checkoutTotal === null ? '—' : '¥'.number_format($checkoutTotal) }}</dd>
                        </div>
                    </dl>
                    {{-- <p class="mt-3 text-xs text-ink-muted">{{ __('buyer.checkout.shipping_fee_note') }}</p> --}}
                </div>

                @if ($isStripeGateway)
                    <div class="rounded-lg border border-line bg-surface p-6 shadow-sm">
                        <h2 class="text-base font-semibold text-ink">{{ __('buyer.checkout.card_section') }}</h2>

                        @if ($cardStepReady)
                            {{-- Two-phase, deliberately (CLAUDE.md §14
                            stripe integration incident writeup,
                            CONVENTIONS.md): the Payment Element mounts
                            ONLY once a real PaymentIntent exists
                            (proceedToPayment(), triggered by the buyer's
                            own submit click below), initialized directly
                            with THAT intent's client secret --
                            structurally the only intent the browser can
                            ever confirm, closing the two-intent mismatch
                            that broke webhook confirmation live. The
                            mount <div> stays in the DOM the whole time
                            (x-show, never a Blade @if) so
                            $refs.paymentElement always resolves whenever
                            proceedToPayment() actually needs it. --}}
                            <p x-show="!cardMounted" class="mt-2 text-sm text-ink-muted">{{ __('buyer.checkout.proceed_to_payment_help') }}</p>

                            <div x-show="cardMounted">
                                <p class="mt-1 text-xs text-ink-muted">{{ __('buyer.checkout.card_section_help') }}</p>
                                <div x-ref="paymentElement" class="mt-4"></div>
                            </div>

                            <p x-show="errorMessage" x-cloak x-text="errorMessage" class="mt-2 text-sm text-red-600" role="alert"></p>
                        @else
                            {{-- No address yet -- the form above stays on
                            the plain wire:submit branch and nothing
                            Stripe-related exists, so there's nothing to
                            wipe by re-rendering. This is the upfront block
                            called for in the checkout fix: guide the buyer
                            to pick/add an address before they ever reach
                            card entry, instead of erroring after the fact. --}}
                            <p class="mt-2 text-sm text-amber-700">{{ __('buyer.checkout.address_required_for_payment') }}</p>
                        @endif
                    </div>
                @endif
            @endif

            @error('pay') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <button
                type="submit"
                @if ($cardStepReady)
                    :disabled="processing"
                    {{-- Also briefly disabled while a shipping-method change
                    is re-rendering the total, so the buyer clicks on the
                    figure they'll be charged. --}}
                    wire:loading.attr="disabled"
                    wire:target="shippingMethod"
                    x-text="processing ? @js(__('buyer.checkout.processing_button')) : (cardMounted ? @js(__('buyer.checkout.pay_button')) : @js(__('buyer.checkout.proceed_to_payment_button')))"
                @else
                    wire:loading.attr="disabled"
                    wire:target="{{ $partRequest->is_free ? 'confirmFree' : 'pay,shippingMethod' }}"
                    {{-- Stripe + no address yet: block the pay step itself
                    rather than let the buyer submit into a late
                    "select an address" error (see the Card details
                    section above for the upfront message). --}}
                    @if ($isStripeGateway && ! $partRequest->is_free && ! $selectedAddressId) disabled @endif
                @endif
                class="w-full rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
            >{{ $partRequest->is_free ? __('buyer.checkout.confirm_free_button') : ($cardStepReady ? __('buyer.checkout.proceed_to_payment_button') : __('buyer.checkout.pay_button')) }}</button>
        </form>
    @endif

    {{-- Kept inside the single root <div> for the same reason as the
    Stripe.js <script src> above -- see that comment. --}}
    @if ($isStripeGateway && ! $partRequest->is_free)
        @once
            <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('stripeCheckoutForm', (config) => ({
                    stripe: null,
                    elements: null,
                    processing: false,
                    errorMessage: '',
                    // True once the real PaymentIntent exists and its
                    // Payment Element is actually mounted (see
                    // proceedToPayment() below). Everything before this
                    // point is just "pick an address, then click
                    // through" -- no Stripe object exists yet at all.
                    cardMounted: false,
                    redirectUrl: null,

                    init() {
                        this.stripe = Stripe(config.publishableKey);
                        // A fresh checkout creates nothing until the buyer
                        // commits (see proceedToPayment()'s own comment).
                        // A resumed checkout already committed: fetch the
                        // existing intent and mount the card form right
                        // away -- resumePayment() only ever retrieves.
                        if (config.resume) {
                            this.proceedToPayment();
                        }
                    },

                    // Step 1 of 2, triggered by the buyer's own submit
                    // click while !cardMounted. Runs CheckoutAction
                    // server-side (creates the Payment row + real
                    // PaymentIntent) and, only on success, initializes
                    // Stripe Elements *directly with that PaymentIntent's
                    // client secret* -- `stripe.elements({clientSecret})`,
                    // not the account-wide "deferred" mode
                    // (`{mode: 'payment', amount, currency}`) this page
                    // used before. That distinction is the actual fix for
                    // a live bug: with deferred-mode Elements, Stripe.js
                    // computes its own eligible-payment-methods state
                    // independently of any one PaymentIntent, and a
                    // separately-passed clientSecret at confirm time is
                    // not guaranteed to be the intent that actually gets
                    // confirmed -- live testing hit exactly this: the
                    // webhook received a PaymentIntent id with no
                    // matching Payment row at all, because the browser
                    // had confirmed a *different* intent than the one
                    // CheckoutAction created and stored. Initializing
                    // Elements with the real clientSecret from the start
                    // makes that structurally impossible: there is only
                    // ever one PaymentIntent anywhere in this component's
                    // JS from this point on, and confirmPayment() below
                    // doesn't even need to pass clientSecret separately
                    // any more -- elements already carries it. See
                    // CONVENTIONS.md for the full incident writeup.
                    //
                    // This step is exactly the buyer's own "commit to
                    // paying" moment, not a side effect of merely loading
                    // the page (CheckoutAction moves part_requests.status
                    // to `awaiting_payment`) -- deliberately gated
                    // behind this explicit click rather than running from
                    // init(), so a buyer who never gets this far never
                    // triggers a charge attempt at all.
                    async proceedToPayment() {
                        if (this.processing) {
                            return;
                        }

                        this.processing = true;
                        this.errorMessage = '';

                        let result;
                        try {
                            // Resume mode re-fetches the SAME
                            // PaymentIntent; it never runs CheckoutAction.
                            // A fresh checkout sends the total the buyer
                            // is looking at, so pay() can refuse to
                            // create an intent for any other amount.
                            result = config.resume ? await this.$wire.resumePayment() : await this.$wire.pay(this.shownTotal());
                        } catch (e) {
                            this.errorMessage = @js(__('buyer.checkout.error_generic'));
                            this.processing = false;
                            return;
                        }

                        if (result && result.error) {
                            this.errorMessage = result.error;
                            this.processing = false;
                            return;
                        }

                        if (!result || !result.clientSecret) {
                            this.processing = false;
                            return;
                        }

                        this.redirectUrl = result.redirectUrl;

                        this.elements = this.stripe.elements({
                            clientSecret: result.clientSecret,
                            appearance: {theme: 'stripe'}, // light -- this app has no dark mode (CLAUDE.md)
                            // Without this, Stripe auto-detects the card
                            // field labels/placeholders from the browser's
                            // own locale -- this app's UI is English-only
                            // (CLAUDE.md §2), so the card form must be too,
                            // regardless of the buyer's browser language.
                            locale: 'en',
                        });

                        this.elements.create('payment').mount(this.$refs.paymentElement);
                        this.cardMounted = true;
                        this.processing = false;
                    },

                    // The total currently rendered in the order summary
                    // (server-computed for the chosen shipping method).
                    shownTotal() {
                        const total = this.$root.querySelector('[data-checkout-total]')?.dataset.checkoutTotal;

                        return total ? Number(total) : null;
                    },

                    // Step 2 of 2, triggered by the buyer's own submit
                    // click once cardMounted -- confirms the SAME
                    // PaymentIntent elements was initialized with above.
                    // Never what marks the payment confirmed (CLAUDE.md
                    // §14 stripe integration) -- that's the webhook's job
                    // alone, already fired (or about to) on Stripe's own
                    // timeline, independent of anything happening in this
                    // browser tab. A decline here leaves the PaymentIntent
                    // at `requires_payment_method` on Stripe's side --
                    // safe to call confirmPayment() again with updated
                    // card details, which is exactly what a second submit
                    // click does; CheckoutAction is never re-run for a
                    // retry, only this step repeats.
                    async submit() {
                        if (this.processing) {
                            return;
                        }

                        this.processing = true;
                        this.errorMessage = '';

                        const {error: submitError} = await this.elements.submit();
                        if (submitError) {
                            this.errorMessage = submitError.message;
                            this.processing = false;
                            return;
                        }

                        // redirect: 'if_required' -- handles 3D
                        // Secure/redirect-requiring cards itself if
                        // needed; no clientSecret passed here, elements
                        // already carries the one real PaymentIntent it
                        // was created with above.
                        const {error: confirmError} = await this.stripe.confirmPayment({
                            elements: this.elements,
                            confirmParams: {return_url: this.redirectUrl},
                            redirect: 'if_required',
                        });

                        if (confirmError) {
                            this.errorMessage = confirmError.message;
                            this.processing = false;
                            return;
                        }

                        window.location.href = this.redirectUrl;
                    },
                }));
            });
            </script>
        @endonce
    @endif
</div>
