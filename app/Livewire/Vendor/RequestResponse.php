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
            'comment' => ['required', 'string', 'max:2000'],
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['image', 'max:10240'],
        ];
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
                'comment' => $validated['comment'],
                'is_no_stock' => false,
            ], $validated['photos']);
        } catch (VendorResponseNotAllowedException $e) {
            report($e);
            $this->addError('cost_price', __('vendor.request_response.submit_error'));

            return;
        }

        $this->submitted = true;
        $this->reset(['cost_price', 'quality_rank', 'lead_time', 'comment', 'photos']);
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
            $this->addError('cost_price', __('vendor.request_response.submit_error'));

            return;
        }

        $this->submitted = true;
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
            ->select(['id', 'request_code', 'part_type', 'maker', 'car_model', 'vin', 'oem_part_number', 'part_name', 'reference_url', 'memo', 'created_at'])
            ->findOrFail($this->partRequestId);

        $myResponse = VendorResponse::query()
            ->with('photos')
            ->where('part_request_id', $this->partRequestId)
            ->where('vendor_id', $this->vendorProfile()->id)
            ->first();

        return view('livewire.vendor.request-response', [
            'partRequest' => $partRequest,
            'myResponse' => $myResponse,
        ])->title($partRequest->request_code);
    }
}
