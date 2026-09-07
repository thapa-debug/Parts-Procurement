<?php

namespace App\Models;

use App\Enums\LeadTime;
use App\Enums\QualityRank;
use Database\Factories\VendorResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'part_request_id', 'vendor_id', 'cost_price', 'quality_rank',
    'lead_time', 'comment', 'weight_kg', 'length_cm', 'width_cm',
    'height_cm', 'is_no_stock',
])]
class VendorResponse extends Model
{
    /** @use HasFactory<VendorResponseFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quality_rank' => QualityRank::class,
        'lead_time' => LeadTime::class,
        'is_no_stock' => 'boolean',
        'weight_kg' => 'decimal:2',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<PartRequest, $this>
     */
    public function partRequest(): BelongsTo
    {
        return $this->belongsTo(PartRequest::class);
    }

    /**
     * @return BelongsTo<VendorProfile, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    /**
     * @return HasMany<ResponsePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ResponsePhoto::class);
    }
}
