<?php

namespace App\Http\Requests;

use App\Enums\PartType;
use App\Models\PartRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
            'maker' => ['required', 'string', 'max:255'],
            'car_model' => ['required', 'string', 'max:255'],
            'vin' => ['nullable', 'string', 'max:255'],
            'mfg_date' => ['nullable', 'date'],
            'oem_part_number' => ['nullable', 'string', 'max:255'],
            'part_name' => ['required', 'string', 'max:255'],
            'reference_url' => ['nullable', 'url', 'max:2048'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
