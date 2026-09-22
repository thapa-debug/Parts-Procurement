<?php

namespace App\Livewire\Buyer;

use App\Actions\CheckoutAction;
use App\Actions\ConfirmFreeOrderAction;
use App\Actions\CreateBuyerAddressAction;
use App\Enums\RequestStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\FreeOrderNotAllowedException;
use App\Exceptions\PaymentFailedException;
use App\Exceptions\ShippingAddressNotAllowedException;
use App\Http\Requests\StoreBuyerAddressRequest;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Stripe\StripeClient;
use Throwable;

/**
 * 有償 checkout (CLAUDE.md §14 Phase 4): pick a saved address (or add one
 * inline), see the fee breakdown, and pay via CheckoutAction. There is no
 * shipping method to pick any more (rule-based shipping v1): the fee was
 * already fixed by SelectQuoteAction the moment the buyer chose their
 * quote, so this screen only ever displays it. Only the id is kept as
 * component state, and render() re-fetches with an explicit narrow
 * select() -- same serialization-safety discipline as
 * App\Livewire\Buyer\RequestDetail: cost_price/applied_rate/
 * selected_response_id must never reach the buyer's own browser.
 *
 * Also serves the 無償 (free) flow (CLAUDE.md §14 Phase 4 slice 5) on this
 * same screen, per the confirmed design: a free request skips payment
 * entirely, but the buyer still confirms/picks a shipping address --
 * their default isn't necessarily right for this particular shipment. The
 * Blade view branches on $partRequest->is_free to show the address picker
 * either alongside the fee breakdown + pay() (real charge, CheckoutAction)
 * or alone + confirmFree() (¥0 confirmed payment, ConfirmFreeOrderAction).
 * Two sibling methods calling two sibling actions, never one shared method
 * with a flag (CLAUDE.md §8) -- each is already guarded against running on
 * the other's kind of request by its own action.
 */
class Checkout extends Component
{
    public int $partRequestId;

    public ?int $selectedAddressId = null;

    public bool $showNewAddressForm = false;

    public string $recipient_name = '';

    public string $phone = '';

    public string $postal_code = '';

    public string $country_id = '';

    public string $state = '';

    public string $city = '';

    public string $address_line1 = '';

    public string $address_line2 = '';

    public function mount(PartRequest $partRequest): void
    {
        // Two abilities, same buyer-and-owner check underneath (CLAUDE.md
        // §14 Phase 4 slice 5) -- which one applies depends on is_free,
        // exactly the same branching the Blade view and pay()/confirmFree()
        // below use.
        $this->authorize($partRequest->is_free ? 'confirmFreeOrder' : 'checkout', $partRequest);

        $this->partRequestId = $partRequest->id;

        $defaultAddressId = $this->buyer()->addresses()->where('is_default', true)->value('id');
        $this->selectedAddressId = $defaultAddressId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'selectedAddressId' => ['required', 'integer', Rule::exists('buyer_addresses', 'id')->where('buyer_id', $this->buyer()->id)],
        ];
    }

    public function toggleNewAddressForm(): void
    {
        $this->showNewAddressForm = ! $this->showNewAddressForm;
        $this->resetErrorBag(['recipient_name', 'phone', 'postal_code', 'country_id', 'state', 'city', 'address_line1', 'address_line2']);
    }

    public function addAddress(CreateBuyerAddressAction $action): void
    {
        $this->authorize('create', BuyerAddress::class);

        // is_default is deliberately dropped -- Checkout has no such
        // property (an address added here is simply auto-selected for
        // this checkout, not necessarily made the buyer's default).
        $rules = (new StoreBuyerAddressRequest)->rules();
        unset($rules['is_default']);

        $validated = $this->validate($rules);
        $validated['country_id'] = (int) $validated['country_id'];
        $validated['state'] = $validated['state'] ?: null;
        $validated['address_line2'] = $validated['address_line2'] ?: null;

        $address = $action->execute($this->buyer(), $validated);

        $this->selectedAddressId = $address->id;
        $this->showNewAddressForm = false;
        $this->reset(['recipient_name', 'phone', 'postal_code', 'country_id', 'state', 'city', 'address_line1', 'address_line2']);

        $isFree = PartRequest::findOrFail($this->partRequestId)->is_free;

        if ($this->cardStepReady($isFree)) {
            // A normal in-place Livewire update from here is unsafe once
            // the Stripe-driven card step is (or is about to become) part
            // of the page: the <form> is gaining x-data/x-init for the
            // first time mid-session -- and Alpine only reliably
            // initializes a directive when the element carrying it is
            // first inserted into the DOM, not when an already-existing
            // element's attributes change underneath it via a Livewire
            // morph, which is exactly what happens here (the buyer's very
            // first address turns this form Stripe-driven). Symptom
            // reported live: the Pay button rendered half-initialized
            // after adding a first address -- collapsed height, its
            // x-text never actually bound. A full page reload sidesteps
            // all of it: it boots Alpine fresh, exactly like the buyer's
            // original page load, with the new address already selected.
            $this->redirect(route('buyer.requests.checkout', $this->partRequestId));

            return;
        }

        $this->dispatch('toast', message: __('buyer.checkout.address_added'), type: 'success');
    }

    /**
     * For the stub gateway (dev/test), CheckoutAction's own success is
     * already the whole story -- redirect immediately, same as always.
     *
     * For Stripe, this is the server-side half of the buyer's explicit
     * "Proceed to payment" click (resources/views/livewire/buyer/
     * checkout.blade.php's Alpine proceedToPayment()) -- CheckoutAction's
     * success only means "the PaymentIntent was created" (CLAUDE.md §14
     * stripe integration), nothing has been confirmed yet. This returns
     * the PaymentIntent's client secret to the browser, which then
     * initializes Stripe Elements *directly with that client secret*
     * (`stripe.elements({clientSecret})`) rather than the account's
     * generic "deferred" mode -- deliberately, so there is structurally
     * only ever one PaymentIntent in play client-side, the exact one
     * this method just created and stored via
     * `$payment->update(['gateway_reference' => $intent->id])`
     * (StripePaymentGateway::charge()). A live bug traced to the account's
     * previous deferred-Elements setup (`stripe.elements({mode: ...})`
     * + a separately-passed clientSecret at confirm time) letting the
     * buyer's browser end up confirming a *different* PaymentIntent than
     * the one stored here -- the webhook then had no matching Payment row
     * at all ("unknown PaymentIntent"), so the gate never opened. See
     * CONVENTIONS.md for the full incident writeup. The client secret
     * itself is never persisted (StripePaymentGateway deliberately
     * doesn't store it -- Stripe's own docs: "should not be stored,
     * logged, or exposed to anyone other than the customer") -- it's
     * re-fetched here, fresh, for this one response, by retrieving the
     * PaymentIntent CheckoutAction/StripePaymentGateway already created
     * and stored the id of.
     *
     * Either way, actual confirmation is the webhook's job alone
     * (ConfirmStripePaymentAction) -- nothing here, and nothing the
     * browser does with this client secret, ever marks the payment
     * confirmed itself.
     *
     * @return array{clientSecret: string, redirectUrl: string}|array{error: string}|null
     */
    public function pay(CheckoutAction $action): ?array
    {
        $partRequest = PartRequest::findOrFail($this->partRequestId);
        $this->authorize('checkout', $partRequest);

        // True exactly when Blade would have rendered the Stripe-driven
        // "Proceed to payment" step for this same state (cardStepReady()
        // -- $isFree is always false here since pay() is never
        // legitimately called against a free request, CheckoutAction
        // rejects that itself). Once this method succeeds, CheckoutAction
        // has already flipped part_requests.status to `paid` -- if this
        // response were allowed to render normally, $isEligible in
        // render() below would immediately flip to false (it requires
        // status === quoted) and Livewire would morph the whole checkout
        // form away into the "not eligible" message, right as the
        // browser's own JS continuation tries to mount the Payment
        // Element into a <div> that would no longer exist. skipRender()
        // next, applied before validate() can even throw, means every
        // failure from here on *also* reports back through this method's
        // return value instead of the error bag -- never a re-render,
        // for the same reason. A buyer who hasn't reached the card step
        // yet (no address selected) keeps the classic
        // validate-then-error-bag behaviour Blade's own
        // @error('selectedAddressId') already renders.
        $protectCardForm = $this->cardStepReady(isFree: false);

        if ($protectCardForm) {
            $this->skipRender();
        }

        try {
            $validated = $this->validate();
        } catch (ValidationException $e) {
            if ($protectCardForm) {
                return ['error' => $e->validator->errors()->first()];
            }

            throw $e;
        }

        $address = BuyerAddress::findOrFail($validated['selectedAddressId']);

        try {
            $result = $action->execute($partRequest, $address);

            if ($this->isStripeGateway()) {
                $payment = Payment::query()
                    ->where('part_request_id', $result->id)
                    ->where('gateway', 'stripe')
                    ->latest('id')
                    ->firstOrFail();

                $clientSecret = app(StripeClient::class)
                    ->paymentIntents
                    ->retrieve($payment->gateway_reference)
                    ->client_secret;

                return [
                    'clientSecret' => $clientSecret,
                    'redirectUrl' => route('buyer.requests.show', $result->id),
                ];
            }
        } catch (CheckoutNotAllowedException|ShippingAddressNotAllowedException $e) {
            report($e);

            return $this->reportPayError(__('buyer.checkout.error_not_allowed'), $protectCardForm);
        } catch (PaymentFailedException $e) {
            Log::channel('payments')->warning('Checkout payment declined at the UI layer', ['part_request_id' => $partRequest->id]);
            report($e);

            return $this->reportPayError(__('buyer.checkout.error_payment_failed'), $protectCardForm);
        } catch (Throwable $e) {
            report($e);

            return $this->reportPayError(__('buyer.checkout.error_generic'), $protectCardForm);
        }

        // A toast alone is unreliable here: $this->redirect() below is a
        // full browser navigation, which can wipe an Alpine-side toast
        // before the buyer ever sees it. A flashed session('status') banner
        // (same mechanism as PasswordChange) survives the navigation and
        // renders on the destination page instead -- durable confirmation,
        // not a transient one, for the money-critical "did my payment go
        // through" moment.
        session()->flash('status', __('buyer.checkout.paid', ['code' => $result->request_code]));
        $this->redirect(route('buyer.requests.show', $result->id));

        return null;
    }

    /**
     * Single source of truth for "which gateway is actually bound right
     * now" -- both render() (which UI to show) and pay() (which success
     * path to take) read this, rather than each re-deriving it.
     */
    protected function isStripeGateway(): bool
    {
        return app(PaymentGateway::class)->name() === 'stripe';
    }

    /**
     * Reports a pay() failure the right way for whichever state produced
     * it. Once the Stripe card form is mounted ($protectCardForm), this
     * response is already skipRender()'d (see pay() above) -- an error
     * here must travel back through the return value, same as success,
     * or it would simply vanish. Before that point (no address selected
     * yet, nothing mounted to protect) it keeps the plain error-bag path
     * Blade's own @error('pay') already renders.
     *
     * @return array{error: string}|null
     */
    protected function reportPayError(string $message, bool $protectCardForm): ?array
    {
        if ($protectCardForm) {
            return ['error' => $message];
        }

        $this->addError('pay', $message);

        return null;
    }

    /**
     * The 無償 (free) mirror of pay() above -- same address validation and
     * redirect shape, but confirms via ConfirmFreeOrderAction instead of
     * charging anything.
     */
    public function confirmFree(ConfirmFreeOrderAction $action): void
    {
        $partRequest = PartRequest::findOrFail($this->partRequestId);
        $this->authorize('confirmFreeOrder', $partRequest);

        $validated = $this->validate();

        $address = BuyerAddress::findOrFail($validated['selectedAddressId']);

        try {
            $result = $action->execute($partRequest, $address);
        } catch (FreeOrderNotAllowedException|ShippingAddressNotAllowedException $e) {
            report($e);
            $this->addError('pay', __('buyer.checkout.error_not_allowed'));

            return;
        } catch (Throwable $e) {
            report($e);
            $this->addError('pay', __('buyer.checkout.error_generic'));

            return;
        }

        session()->flash('status', __('buyer.checkout.free_confirmed', ['code' => $result->request_code]));
        $this->redirect(route('buyer.requests.show', $result->id));
    }

    protected function buyer(): BuyerProfile
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var BuyerProfile $buyer */
        $buyer = $user->buyerProfile;

        return $buyer;
    }

    /**
     * @return Collection<int, BuyerAddress>
     */
    protected function addresses(): Collection
    {
        return $this->buyer()->addresses()->with('country')->orderByDesc('is_default')->orderByDesc('id')->get();
    }

    /**
     * Single source of truth for "is the buyer eligible to reach the
     * Stripe-driven card step right now (or about to be, on this very
     * response)" -- render(), addAddress(), and pay()'s own
     * $protectCardForm all read this rather than repeating the condition
     * (pay() never needs $isFree: it's never legitimately called against
     * a free request in the first place).
     *
     * NOT the same thing as the Payment Element actually being mounted in
     * the browser -- that's a separate, later, buyer-driven step (see
     * checkout.blade.php's Alpine `cardMounted`/proceedToPayment()): this
     * only gates whether the card step is *reachable* (stripe active, not
     * a free request, and a shipping address already chosen).
     */
    protected function cardStepReady(bool $isFree): bool
    {
        return $this->isStripeGateway() && ! $isFree && $this->selectedAddressId !== null;
    }

    public function render(): View
    {
        // Fetched separately from the main query below, and never selected
        // onto the $partRequest passed to the view -- same discipline as
        // RequestDetail: a raw internal FK to a vendor_response row has no
        // reason to ever reach the buyer's browser.
        $isEligible = PartRequest::query()
            ->where('id', $this->partRequestId)
            ->where('status', RequestStatus::Quoted)
            ->whereNotNull('selected_response_id')
            ->whereNotNull('shipping_fee')
            ->exists();

        // Explicit column allowlist -- cost_price, applied_rate,
        // applied_min_fee, and selected_response_id are deliberately
        // absent, same discipline as RequestDetail. buyer_price and
        // shipping_fee (already fixed by SelectQuoteAction) are the only
        // price columns a buyer is ever allowed to see.
        $partRequest = PartRequest::query()
            ->select(['id', 'request_code', 'status', 'buyer_price', 'shipping_fee', 'is_free'])
            ->findOrFail($this->partRequestId);

        $isStripeGateway = $this->isStripeGateway();

        return view('livewire.buyer.checkout', [
            'partRequest' => $partRequest,
            'isEligible' => $isEligible,
            'addresses' => $this->addresses(),
            'countryOptions' => Country::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'isStripeGateway' => $isStripeGateway,
            // Everywhere the view needs to know whether the card step is
            // reachable (the address radios' binding mode, the form's
            // x-data, the card section itself, the submit button) reads
            // this one flag -- see cardStepReady()'s own docblock.
            'cardStepReady' => $this->cardStepReady($partRequest->is_free),
            // The publishable key, not the secret -- safe to expose
            // client-side by design (that's what "publishable" means).
            // Only actually used by the view when isStripeGateway is true.
            'stripePublishableKey' => config('services.stripe.key'),
        ])->title(__('buyer.checkout.title', ['code' => $partRequest->request_code]));
    }
}
