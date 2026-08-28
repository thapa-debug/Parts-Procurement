<?php

namespace App\Http\Requests;

use App\Enums\PartType;
use App\Models\PartRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SubmitPartRequestRequest extends FormRequest
{
    /**
     * Structural check only (PartRequestPolicy::create) -- a buyer, full
     * stop. The `act` gate (verified + approved) is a separate, additional
     * check the caller makes via authorize('act') per CONVENTIONS.md, not
     * folded in here.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', PartRequest::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'part_type' => ['required', new Enum(PartType::class)],
            // Never trust the <select> alone -- validate server-side against
            // the same fixed list it's built from (lang/en/buyer.php).
            'maker' => ['required', 'string', Rule::in(__('buyer.request_form.maker_options'))],
            'car_model' => ['required', 'string', 'max:255'],
            'vin' => ['nullable', 'string', 'max:255'],
            // Year and month only (e.g. "2005/10") -- see the part_requests
            // migration for why this is a plain string, not a date.
            'mfg_date' => ['nullable', 'string', 'regex:/^\d{4}\/(0[1-9]|1[0-2])$/'],
            'oem_part_number' => ['nullable', 'string', 'max:255'],
            'part_name' => ['required', 'string', 'max:255'],
            'reference_url' => ['nullable', 'url', 'max:2048'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mfg_date.regex' => __('buyer.request_form.mfg_date_format_error'),
        ];
    }
}
