<?php

namespace App\Http\Requests;

use App\Models\BuyerProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBuyerRequest extends FormRequest
{
    /**
     * Only an admin may create a buyer account this way (BuyerProfilePolicy::create).
     * Self-registration is a separate, unauthenticated path -- see RegisterBuyerAction.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', BuyerProfile::class) ?? false;
    }

    /**
     * member_code is intentionally absent -- it's system-generated
     * (BuyerProfile::generateMemberCode), never a form field.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'company_name' => ['required', 'string', 'max:255'],
            // Active only -- this is a fresh selection, unlike editing an
            // existing buyer, where a since-deactivated country the buyer
            // already has must stay accepted (see BuyerDetail::rules()).
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
            'phone' => ['required', 'string', 'max:255'],
            // CLAUDE.md §14's buyer-approval gate: whether this admin-created
            // buyer is approved immediately or lands in the pending queue.
            // Defaults to true (checked) in the form -- "activate now" is the
            // common case for a buyer the admin is deliberately creating.
            'approve_immediately' => ['required', 'boolean'],
        ];
    }
}
