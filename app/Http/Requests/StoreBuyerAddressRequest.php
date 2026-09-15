<?php

namespace App\Http\Requests;

use App\Models\BuyerAddress;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules for a brand-new BuyerAddress -- used by both AddressBook's "add"
 * form and Checkout's inline "add an address" form (CLAUDE.md §14 Phase 4
 * slice 3). Editing an existing address is deliberately NOT this class --
 * see AddressBook::rules(), which differs only in the country_id rule
 * (an existing row keeps a since-deactivated country, same reasoning as
 * BuyerDetail::rules()).
 */
class StoreBuyerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BuyerAddress::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:255'],
            // Active only -- this is a fresh selection, unlike editing an
            // existing address (see AddressBook::rules()).
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ];
    }
}
