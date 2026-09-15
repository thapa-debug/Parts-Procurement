<?php

namespace App\Livewire\Buyer;

use App\Actions\CreateBuyerAddressAction;
use App\Actions\DeleteBuyerAddressAction;
use App\Actions\SetDefaultBuyerAddressAction;
use App\Actions\UpdateBuyerAddressAction;
use App\Http\Requests\StoreBuyerAddressRequest;
use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The buyer's own reusable address book (CLAUDE.md §14 Phase 4 slice 2/3)
 * -- add any number, edit, delete, mark one default. One shared form for
 * both add and edit (editingAddressId null vs set), rather than a second
 * set of "editing_*" fields -- unlike Settings' single-field country/maker
 * rows, this form has eight fields, so duplicating them all would be worse
 * than the one branch in rules()/save() that needs it.
 */
class AddressBook extends Component
{
    public bool $showForm = false;

    public ?int $editingAddressId = null;

    public string $recipient_name = '';

    public string $phone = '';

    public string $postal_code = '';

    public string $country_id = '';

    public string $state = '';

    public string $city = '';

    public string $address_line1 = '';

    public string $address_line2 = '';

    public bool $is_default = false;

    public function mount(): void
    {
        $this->authorize('viewAny', BuyerAddress::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function rules(): array
    {
        if ($this->editingAddressId === null) {
            return (new StoreBuyerAddressRequest)->rules();
        }

        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:255'],
            // Not restricted to active countries, unlike StoreBuyerAddressRequest:
            // this address may already carry a country the admin has since
            // deactivated, and resubmitting the form unchanged must not
            // fail just because that selection is no longer offered for
            // *new* picks (same reasoning as BuyerDetail::rules()).
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ];
    }

    public function startCreate(): void
    {
        $this->authorize('create', BuyerAddress::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function startEdit(int $addressId): void
    {
        $address = BuyerAddress::findOrFail($addressId);
        $this->authorize('update', $address);

        $this->editingAddressId = $address->id;
        $this->recipient_name = $address->recipient_name;
        $this->phone = $address->phone;
        $this->postal_code = $address->postal_code;
        $this->country_id = (string) $address->country_id;
        $this->state = (string) $address->state;
        $this->city = $address->city;
        $this->address_line1 = $address->address_line1;
        $this->address_line2 = (string) $address->address_line2;
        $this->is_default = $address->is_default;
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function save(CreateBuyerAddressAction $createAction, UpdateBuyerAddressAction $updateAction): void
    {
        $validated = $this->validate();
        $validated['country_id'] = (int) $validated['country_id'];
        $validated['state'] = $validated['state'] ?: null;
        $validated['address_line2'] = $validated['address_line2'] ?: null;

        if ($this->editingAddressId === null) {
            $this->authorize('create', BuyerAddress::class);

            /** @var User $user */
            $user = auth()->user();
            /** @var BuyerProfile $buyer */
            $buyer = $user->buyerProfile;

            $createAction->execute($buyer, $validated);
            $message = __('buyer.address_book.created');
        } else {
            $address = BuyerAddress::findOrFail($this->editingAddressId);
            $this->authorize('update', $address);

            $updateAction->execute($address, $validated);
            $message = __('buyer.address_book.updated');
        }

        $this->cancel();
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function delete(int $addressId, DeleteBuyerAddressAction $action): void
    {
        $address = BuyerAddress::findOrFail($addressId);
        $this->authorize('delete', $address);

        $action->execute($address);

        $this->dispatch('toast', message: __('buyer.address_book.deleted'), type: 'success');
    }

    public function setDefault(int $addressId, SetDefaultBuyerAddressAction $action): void
    {
        $address = BuyerAddress::findOrFail($addressId);
        $this->authorize('update', $address);

        $action->execute($address);

        $this->dispatch('toast', message: __('buyer.address_book.default_updated'), type: 'success');
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingAddressId', 'recipient_name', 'phone', 'postal_code',
            'country_id', 'state', 'city', 'address_line1', 'address_line2', 'is_default',
        ]);
        $this->resetErrorBag();
    }

    /**
     * Active countries, plus the address currently being edited's own
     * country even if it's since been deactivated -- same reasoning as
     * BuyerDetail's own country dropdown.
     *
     * @return Collection<int, string>
     */
    protected function countryOptions(): Collection
    {
        $editingCountryId = $this->editingAddressId !== null
            ? BuyerAddress::find($this->editingAddressId)?->country_id
            : null;

        return Country::query()
            ->where('is_active', true)
            ->when($editingCountryId, fn ($query) => $query->orWhere('id', $editingCountryId))
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var BuyerProfile $buyer */
        $buyer = $user->buyerProfile;

        $addresses = $buyer->addresses()
            ->with('country')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return view('livewire.buyer.address-book', [
            'addresses' => $addresses,
            'countryOptions' => $this->countryOptions(),
        ])->title(__('buyer.address_book.title'));
    }
}
