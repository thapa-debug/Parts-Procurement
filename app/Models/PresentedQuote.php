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
 * can't drift what the buyer was already shown for this option. Same
 * discipline for shipping_fee (CLAUDE.md §14 Phase 4 rule-based shipping
 * v1): computed fresh via ShippingCalculator (or admin-overridden, with a
 * required shipping_fee_override_reason -- admin-internal, never shown to
 * the buyer) at presentation time, then frozen even if
 * shipping_weight_brackets changes later.
 *
 * is_free (無償 flow, CLAUDE.md §14 Phase 4): an admin-discretionary flag,
 * set here at presentation time, that forces buyer_price and shipping_fee
 * on this same row to 0 -- cost_price/applied_rate/applied_min_fee are
 * still the real, normally-computed figures, preserving what the admin
 * actually owes the vendor and what the margin would have been.
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
    'shipping_fee', 'shipping_fee_overridden', 'shipping_fee_override_reason',
    'is_free',
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
        'shipping_fee_overridden' => 'boolean',
        'is_free' => 'boolean',
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
            'shipping_fee', 'shipping_fee_overridden', 'shipping_fee_override_reason',
            'is_free',
        ]);
    }
}
