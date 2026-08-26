<?php

namespace App\Http\Requests;

use App\Models\VendorProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateVendorRequest extends FormRequest
{
    /**
     * Only an admin may create a vendor account (VendorProfilePolicy::create).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', VendorProfile::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'notify_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
