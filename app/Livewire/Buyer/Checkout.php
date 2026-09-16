<?php

namespace App\Livewire\Buyer;

use App\Actions\CheckoutAction;
use App\Actions\CreateBuyerAddressAction;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\PaymentFailedException;
use App\Exceptions\ShippingAddressNotAllowedException;
use App\Http\Requests\StoreBuyerAddressRequest;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\PartRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

/**
 * 有償 checkout (CLAUDE.md §14 Phase 4 slice 3): pick a saved address (or
 * add one inline), pick vehicle/container shipping (DHL isn't offered --
 * its own slice, see CheckoutAction's docblock), see the fee breakdown,
 * and pay via CheckoutAction. Only the id is kept as component state, and
 * render() re-fetches with an explicit narrow select() -- same
 * serialization-safety discipline as App\Livewire\Buyer\RequestDetail:
 * cost_price/applied_rate/selected_response_id must never reach the
 * buyer's own browser.
 */
class Checkout extends Component
{
    public int $partRequestId;

    public ?int $selectedAddressId = null;

    public string $shippingMethod = '';

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
        $this->authorize('checkout', $partRequest);

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
            'shippingMethod' => ['required', Rule::in([ShippingMethod::Vehicle->value, ShippingMethod::Container->value])],
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
        $shippingMethod = ShippingMethod::from($validated['shippingMethod']);

        try {
            $result = $action->execute($partRequest, $address, $shippingMethod);
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
            ->exists();

        // Explicit column allowlist -- cost_price, applied_rate,
        // applied_min_fee, and selected_response_id are deliberately
        // absent, same discipline as RequestDetail. buyer_price is the one
        // price column a buyer is ever allowed to see.
        $partRequest = PartRequest::query()
            ->select(['id', 'request_code', 'status', 'buyer_price'])
            ->findOrFail($this->partRequestId);

        return view('livewire.buyer.checkout', [
            'partRequest' => $partRequest,
            'isEligible' => $isEligible,
            'addresses' => $this->addresses(),
            'countryOptions' => Country::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'shippingFees' => [
                ShippingMethod::Vehicle->value => (int) Setting::get('shipping_fee_vehicle', 0),
                ShippingMethod::Container->value => (int) Setting::get('shipping_fee_container', 0),
            ],
        ])->title(__('buyer.checkout.title', ['code' => $partRequest->request_code]));
    }
}
