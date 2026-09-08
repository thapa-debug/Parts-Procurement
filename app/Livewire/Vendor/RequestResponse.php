<?php

namespace App\Livewire\Vendor;

use App\Actions\SubmitVendorResponseAction;
use App\Enums\LeadTime;
use App\Enums\QualityRank;
use App\Exceptions\VendorResponseNotAllowedException;
use App\Models\PartRequest;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class RequestResponse extends Component
{
    use WithFileUploads;

    /**
     * Only the id is kept as component state -- never the PartRequest
     * model itself. A public Eloquent-model property gets its full
     * attribute set serialized into Livewire's client-side snapshot, which
     * would put buyer_id (and everything else on the row) into the
     * vendor's own browser regardless of what the Blade view renders.
     * render() below re-fetches with an explicit narrow ->select() every
     * time instead.
     */
    public int $partRequestId;

    public string $cost_price = '';

    public string $quality_rank = '';

    public string $lead_time = '';

    public string $comment = '';

    /**
     * Weight and dimensions of the part as the vendor would ship it --
     * required on a real quote (not on a no-stock reply), so admin->buyer
     * shipping cost can be calculated later (Phase 4 checkout) without
     * going back to the vendor after the fact, while they still have the
     * part in hand to measure accurately.
     */
    public string $weight_kg = '';

    public string $length_cm = '';

    public string $width_cm = '';

    public string $height_cm = '';

    /**
     * Array-typed, but the file input in the Blade view deliberately has no
     * `multiple` HTML attribute, and drag-and-drop never binds `wire:model`
     * either. Livewire's S3 upload driver flatly rejects a request where the
     * client reports more than one file selected in a single change event
     * (`S3DoesntSupportMultipleFileUploads`) -- but a single-file input
     * bound to an array property is a documented, fully S3-safe Livewire
     * pattern: each selection uploads as one file (`$isMultiple` is false),
     * and `WithFileUploads::_finishUpload()` itself appends it onto the
     * existing array rather than replacing it (see the vendor's own "if the
     * property is an array, but the upload ISN'T set to multiple, then
     * APPEND" comment in that method). Both entry points (click-to-browse
     * and drag-and-drop) funnel through the same Alpine `uploadFiles()`
     * helper in the Blade view, which loops over whatever `FileList` it's
     * given and calls `$wire.$upload()` once per file, sequentially --
     * never `$wire.$uploadMultiple()`. removePhoto() below lets the vendor
     * drop one before submitting.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $photos = [];

    /**
     * @var 'unverified'|null
     */
    public ?string $blockedReason = null;

    public bool $submitted = false;

    public function mount(PartRequest $partRequest): void
    {
        $this->authorize('respond', $partRequest);

        $this->partRequestId = $partRequest->id;

        /** @var User $user */
        $user = auth()->user();
        $this->blockedReason = $user->hasVerifiedEmail() ? null : 'unverified';

        $this->submitted = VendorResponse::query()
            ->where('part_request_id', $this->partRequestId)
            ->where('vendor_id', $this->vendorProfile()->id)
            ->exists();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'cost_price' => ['required', 'integer', 'min:1'],
            'quality_rank' => ['required', Rule::enum(QualityRank::class)],
            'lead_time' => ['required', Rule::enum(LeadTime::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
            'weight_kg' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'length_cm' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'width_cm' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'height_cm' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'photos' => ['required', 'array', 'min:1', 'max:'.$this->maxPhotos()],
            'photos.*' => ['image', 'max:10240'],
        ];
    }

    /**
     * Belt-and-suspenders against the configured max: the Blade view hides
     * the drop zone/file input once the cap is reached, but a drag-and-drop
     * or selection made just before that re-render still lands here. Drop
     * whatever's newest beyond the cap rather than silently accepting it.
     */
    public function updatedPhotos(): void
    {
        $max = $this->maxPhotos();

        if (count($this->photos) > $max) {
            $this->photos = array_slice($this->photos, 0, $max);
            $this->addError('photos', __('vendor.request_response.max_photos_error', ['max' => $max]));
        }
    }

    /**
     * Configurable rather than a hardcoded magic number (config/vendor.php)
     * so it's easy to raise or lower later.
     */
    public function maxPhotos(): int
    {
        return (int) config('vendor.max_response_photos', 10);
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
        $this->resetErrorBag('photos');
    }

    public function sendResponse(SubmitVendorResponseAction $action): void
    {
        $this->authorize('act');

        $partRequest = PartRequest::findOrFail($this->partRequestId);
        $this->authorize('respond', $partRequest);

        $validated = $this->validate();

        try {
            $action->execute($partRequest, $this->vendorProfile(), [
                'cost_price' => (int) $validated['cost_price'],
                'quality_rank' => $validated['quality_rank'],
                'lead_time' => $validated['lead_time'],
                'comment' => $validated['comment'] ?: null,
                'weight_kg' => (float) $validated['weight_kg'],
                'length_cm' => (float) $validated['length_cm'],
                'width_cm' => (float) $validated['width_cm'],
                'height_cm' => (float) $validated['height_cm'],
                'is_no_stock' => false,
            ], $validated['photos']);
        } catch (VendorResponseNotAllowedException $e) {
            report($e);
            $message = __('vendor.request_response.submit_error');
            $this->addError('cost_price', $message);
            $this->dispatch('toast', message: $message, type: 'error');

            return;
        }

        $this->submitted = true;
        $this->reset([
            'cost_price', 'quality_rank', 'lead_time', 'comment',
            'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'photos',
        ]);

        $this->dispatch('toast', message: __('vendor.request_response.submitted_quote', [
            'price' => number_format((int) $validated['cost_price']),
            'rank' => strtoupper($validated['quality_rank']),
            'lead_time' => __('enums.lead_time.'.$validated['lead_time']),
        ]), type: 'success');
    }

    public function sendNoStock(SubmitVendorResponseAction $action): void
    {
        $this->authorize('act');

        $partRequest = PartRequest::findOrFail($this->partRequestId);
        $this->authorize('respond', $partRequest);

        try {
            $action->execute($partRequest, $this->vendorProfile(), ['is_no_stock' => true]);
        } catch (VendorResponseNotAllowedException $e) {
            report($e);
            $message = __('vendor.request_response.submit_error');
            $this->addError('cost_price', $message);
            $this->dispatch('toast', message: $message, type: 'error');

            return;
        }

        $this->submitted = true;
        $this->dispatch('toast', message: __('vendor.request_response.submitted_no_stock'), type: 'success');
    }

    protected function vendorProfile(): VendorProfile
    {
        /** @var User $user */
        $user = auth()->user();

        /** @var VendorProfile $vendorProfile */
        $vendorProfile = $user->vendorProfile;

        return $vendorProfile;
    }

    public function render(): View
    {
        $partRequest = PartRequest::query()
            ->select(['id', 'request_code', 'part_type', 'maker_id', 'car_model', 'vin', 'oem_part_number', 'part_name', 'reference_url', 'memo', 'created_at'])
            ->with('maker')
            ->findOrFail($this->partRequestId);

        $myResponse = VendorResponse::query()
            ->with('photos')
            ->where('part_request_id', $this->partRequestId)
            ->where('vendor_id', $this->vendorProfile()->id)
            ->first();

        return view('livewire.vendor.request-response', [
            'partRequest' => $partRequest,
            'myResponse' => $myResponse,
            'maxPhotos' => $this->maxPhotos(),
        ])->title($partRequest->request_code);
    }
}
