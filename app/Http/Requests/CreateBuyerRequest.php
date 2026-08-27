<?php

namespace App\Http\Requests;

use App\Models\BuyerProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'default_destination_country' => ['required', 'string', 'max:255'],
            'default_yard' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
        ];
    }
}
