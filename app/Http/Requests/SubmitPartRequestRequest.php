<?php

namespace App\Http\Requests;

use App\Models\PartRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // part_type is no longer a buyer-facing field (client revision:
            // the client only deals in new parts) -- RequestForm always
            // passes PartType::New to the action directly. Not validated
            // here since it's never submitted.
            // Never trust the <select> alone -- validate server-side against
            // active makers, the same set it's built from.
            'maker_id' => ['required', 'integer', Rule::exists('makers', 'id')->where('is_active', true)],
            'car_model' => ['required', 'string', 'max:255'],
            // Always required, no exceptions (confirmed with the client) --
            // previously conditional on having an OEM part number or
            // reference URL instead; that "at least one" rule is gone.
            'vin' => ['required', 'string', 'max:255'],
            'oem_part_number' => ['nullable', 'string', 'max:255'],
            'part_name' => ['required', 'string', 'max:255'],
            'reference_url' => ['nullable', 'url', 'max:2048'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
