<?php

namespace App\Models;

use App\Enums\PartType;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use Database\Factories\PartRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'buyer_id', 'request_code', 'part_type', 'maker', 'car_model', 'vin',
    'oem_part_number', 'part_name', 'reference_url', 'memo',
    'status', 'applied_rate', 'applied_min_fee', 'buyer_price',
    'shipping_method', 'shipping_fee',
])]
class PartRequest extends Model
{
    /** @use HasFactory<PartRequestFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'part_type' => PartType::class,
        'status' => RequestStatus::class,
        'shipping_method' => ShippingMethod::class,
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(BuyerProfile::class, 'buyer_id');
    }

    /**
     * Vendors this request has been broadcast to (打診, CLAUDE.md §7's
     * request_vendor pivot) -- who, and when they were invited.
     *
     * @return BelongsToMany<VendorProfile, $this, RequestVendorPivot>
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(VendorProfile::class, 'request_vendor', 'part_request_id', 'vendor_id')
            ->using(RequestVendorPivot::class)
            ->withPivot('invited_at');
    }

    /**
     * @return HasMany<VendorResponse, $this>
     */
    public function vendorResponses(): HasMany
    {
        return $this->hasMany(VendorResponse::class);
    }

    /**
     * Internal business ID (e.g. "REQ-000042") -- deterministic from the
     * row's own id, so it needs no separate uniqueness check or retry loop.
     * Same reasoning as BuyerProfile::generateMemberCode(): the id doesn't
     * exist until after insert, so callers generate this in a second step.
     */
    public static function generateRequestCode(int $id): string
    {
        return 'REQ-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
