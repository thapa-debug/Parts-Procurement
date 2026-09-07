<?php

namespace App\Models;

use Database\Factories\PresentedQuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One vendor response currently on offer to the buyer (client revision:
 * multiple quotes may be presented at once, replacing Phase 2's one-shot
 * single-quote model). Each row carries its own price snapshot (CLAUDE.md
 * §6.2 applied per-quote, computed fresh via PricingService the moment it's
 * presented) -- never recomputed afterward, so a later margin-rate change
 * can't drift what the buyer was already shown for this option.
 *
 * Presenting is final by explicit client decision -- there is no admin
 * action to withdraw/remove an already-presented quote, so this row is
 * never deleted (see PresentQuoteAction). LogsActivity below records who
 * presented what, when.
 *
 * Deliberately carries no relation to VendorProfile and no accessor that
 * reaches through to one -- see PartRequestPolicy/RequestDetail (buyer) for
 * the isolation discipline this must never undermine.
 */
#[Fillable([
    'part_request_id', 'vendor_response_id', 'cost_price',
    'applied_rate', 'applied_min_fee', 'buyer_price', 'presented_at',
])]
class PresentedQuote extends Model
{
    /** @use HasFactory<PresentedQuoteFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'presented_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PartRequest, $this>
     */
    public function partRequest(): BelongsTo
    {
        return $this->belongsTo(PartRequest::class);
    }

    /**
     * @return BelongsTo<VendorResponse, $this>
     */
    public function vendorResponse(): BelongsTo
    {
        return $this->belongsTo(VendorResponse::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly([
            'part_request_id', 'vendor_response_id', 'buyer_price',
        ]);
    }
}
