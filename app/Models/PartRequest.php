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
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'buyer_id', 'request_code', 'part_type', 'maker_id', 'car_model', 'vin',
    'oem_part_number', 'part_name', 'reference_url', 'memo',
    'status', 'cost_price', 'applied_rate', 'applied_min_fee', 'buyer_price',
    'selected_response_id', 'confirmed_vendor_id', 'shipping_method', 'shipping_fee',
    'is_free', 'shipping_address_id', 'shipping_recipient_name', 'shipping_phone',
    'shipping_postal_code', 'shipping_country', 'shipping_state',
    'shipping_city', 'shipping_address_line1', 'shipping_address_line2',
])]
class PartRequest extends Model
{
    /** @use HasFactory<PartRequestFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'part_type' => PartType::class,
        'status' => RequestStatus::class,
        'shipping_method' => ShippingMethod::class,
        'is_free' => 'boolean',
    ];

    /**
     * @return BelongsTo<BuyerProfile, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(BuyerProfile::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<Maker, $this>
     */
    public function maker(): BelongsTo
    {
        return $this->belongsTo(Maker::class);
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
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The live BuyerAddress this request's shipping_* snapshot columns were
     * copied from (CLAUDE.md §14 Phase 4 slice 2), kept only for
     * traceability while that row still exists -- null once it's deleted
     * (nullOnDelete), even though the snapshot columns themselves are
     * untouched. Never the source of truth for an already-placed order;
     * read the shipping_* columns directly for that.
     *
     * @return BelongsTo<BuyerAddress, $this>
     */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(BuyerAddress::class, 'shipping_address_id');
    }

    /**
     * Every vendor response currently on offer to the buyer (client
     * revision: multiple quotes may be presented at once). Presenting is
     * final -- there is no admin action that ever removes a row -- so this
     * is also every quote that has ever been presented; see PresentedQuote's
     * own docblock.
     *
     * @return HasMany<PresentedQuote, $this>
     */
    public function presentedQuotes(): HasMany
    {
        return $this->hasMany(PresentedQuote::class);
    }

    /**
     * The buyer's current pick among the presented quotes (CLAUDE.md §6.2) --
     * populated, and re-populated on re-selection, by SelectQuoteAction,
     * alongside the snapshotted price columns copied from that quote's own
     * PresentedQuote row. Despite the name/column staying as it was in
     * Phase 2's single-quote model, this now means "what the buyer picked",
     * not "what the admin presented" -- see SelectQuoteAction's docblock.
     *
     * @return BelongsTo<VendorResponse, $this>
     */
    public function selectedResponse(): BelongsTo
    {
        return $this->belongsTo(VendorResponse::class, 'selected_response_id');
    }

    /**
     * The universal "still open to admin/buyer changes" gate (client
     * revision): presenting, and selecting/re-selecting a quote, are both
     * allowed until the request has actually been paid for. Phase 4 payment
     * doesn't exist yet, so none of these statuses are reachable today --
     * this reads gracefully as "always open" until that lands and starts
     * setting one of them.
     */
    public function hasBeenPaid(): bool
    {
        return in_array($this->status, [
            RequestStatus::Paid,
            RequestStatus::OrderedToVendor,
            RequestStatus::Shipped,
            RequestStatus::Received,
        ], true);
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
