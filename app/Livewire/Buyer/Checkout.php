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
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Component;
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
        $this->dispatch('toast', message: __('buyer.checkout.address_added'), type: 'success');
    }

    public function pay(CheckoutAction $action): void
    {
        $partRequest = PartRequest::findOrFail($this->partRequestId);
        $this->authorize('checkout', $partRequest);

        $validated = $this->validate();

        $address = BuyerAddress::findOrFail($validated['selectedAddressId']);

        try {
            $result = $action->execute($partRequest, $address);
        } catch (CheckoutNotAllowedException|ShippingAddressNotAllowedException $e) {
            report($e);
            $this->addError('pay', __('buyer.checkout.error_not_allowed'));

            return;
        } catch (PaymentFailedException $e) {
            Log::channel('payments')->warning('Checkout payment declined at the UI layer', ['part_request_id' => $partRequest->id]);
            report($e);
            $this->addError('pay', __('buyer.checkout.error_payment_failed'));

            return;
        } catch (Throwable $e) {
            report($e);
            $this->addError('pay', __('buyer.checkout.error_generic'));

            return;
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

        return view('livewire.buyer.checkout', [
            'partRequest' => $partRequest,
            'isEligible' => $isEligible,
            'addresses' => $this->addresses(),
            'countryOptions' => Country::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ])->title(__('buyer.checkout.title', ['code' => $partRequest->request_code]));
    }
}
