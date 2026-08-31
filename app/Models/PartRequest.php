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
