<?php

namespace App\Models;

use App\Enums\PartType;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use Closure;
use Database\Factories\PartRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Lang;

#[Fillable([
    'buyer_id', 'request_code', 'part_type', 'maker_id', 'car_model', 'vin',
    'oem_part_number', 'part_name', 'reference_url', 'memo',
    'status', 'cost_price', 'applied_rate', 'applied_min_fee', 'buyer_price',
    'selected_response_id', 'confirmed_vendor_id', 'shipping_method', 'shipping_fee',
    'is_free', 'shipping_address_id', 'shipping_recipient_name', 'shipping_phone',
    'shipping_postal_code', 'shipping_country', 'shipping_state',
    'shipping_city', 'shipping_address_line1', 'shipping_address_line2',
    'receiving_address_id', 'receiving_label', 'receiving_recipient_name',
    'receiving_phone', 'receiving_postal_code', 'receiving_prefecture',
    'receiving_city', 'receiving_address_line1', 'receiving_address_line2',
    'is_url_sourced', 'url_weight_kg', 'url_shipping_fee', 'url_shipping_fee_overridden',
    'url_shipping_fee_override_reason', 'url_purchase_reference',
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
        'is_url_sourced' => 'boolean',
        'url_weight_kg' => 'decimal:2',
        'url_shipping_fee_overridden' => 'boolean',
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
     * The admin's "quotes received" tallies, in one place for the request
     * board (withCount(), no N+1) and request detail (loadCount()). Adds
     * invited_vendors_count (broadcast targets), quotes_count (real
     * quotes) and no_stock_replies_count (one-tap "no stock" replies). A
     * vendor can reply at most once per request, so each counts once.
     *
     * @return array<int|string, string|Closure>
     */
    public static function responseCounts(): array
    {
        return [
            'vendors as invited_vendors_count',
            'vendorResponses as quotes_count' => fn (Builder $query) => $query->where('is_no_stock', false),
            'vendorResponses as no_stock_replies_count' => fn (Builder $query) => $query->where('is_no_stock', true),
        ];
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
     * The vendor the purchase was confirmed to (ConfirmOrderToVendorAction)
     * -- admin-only context; never exposed to the buyer.
     *
     * @return BelongsTo<VendorProfile, $this>
     */
    public function confirmedVendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'confirmed_vendor_id');
    }

    /**
     * The live CompanyAddress this request's receiving_* snapshot columns
     * were copied from at order confirmation (CLAUDE.md §14 Phase 4 slice
     * 6) -- traceability only, exactly like shippingAddress() above: null
     * once the address is deleted, and never the source of truth for where
     * the vendor was told to deliver. Read the receiving_* columns for that.
     *
     * @return BelongsTo<CompanyAddress, $this>
     */
    public function receivingAddress(): BelongsTo
    {
        return $this->belongsTo(CompanyAddress::class, 'receiving_address_id');
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
     * Whether the buyer's payment has actually been confirmed -- every
     * status from Paid onwards. AwaitingPayment is deliberately NOT paid:
     * checkout started, but no money is confirmed yet.
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
     * An abandoned Stripe checkout the buyer can come back and finish: the
     * request is awaiting_payment, and its Stripe payment is still pending
     * (or was declined) with a PaymentIntent on record. The single source
     * of truth for both the buyer request page's "Complete payment" entry
     * point and the checkout page's resume form -- a cheap DB check only;
     * whether Stripe still lets the intent be paid is decided by
     * ResumeStripePaymentAction when the card form asks for it.
     */
    public function hasResumablePayment(): bool
    {
        return $this->status === RequestStatus::AwaitingPayment
            && ! $this->is_free
            && $this->payments()
                ->where('gateway', 'stripe')
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Failed])
                ->whereNotNull('gateway_reference')
                ->exists();
    }

    /**
     * Whether the buyer has a priced quote to check out: a selected
     * presented quote (vendor flow), or the admin's own price (reference-
     * URL flow, SetUrlPriceAction -- there is no vendor response to select,
     * so selected_response_id stays null). The one place CheckoutAction
     * asks, so it never has to know which front half priced the request.
     */
    public function hasPricedQuote(): bool
    {
        return $this->is_url_sourced
            ? $this->buyer_price !== null
            : $this->selected_response_id !== null;
    }

    /**
     * The universal "still open to admin/buyer changes" gate (client
     * revision): presenting, and selecting/re-selecting a quote, are
     * allowed until the buyer starts paying. Locked from AwaitingPayment
     * on, not just once paid -- the payment was started for the selected
     * quote's amount, so the selection must not change underneath it.
     */
    public function quoteChoiceLocked(): bool
    {
        return $this->status === RequestStatus::AwaitingPayment || $this->hasBeenPaid();
    }

    /**
     * The status label the ADMIN sees (board, request page). A URL-sourced
     * request shares the vendor flow's statuses (MarkPurchasedFromUrlAction
     * moves it to ordered_to_vendor) but has no vendor, so any status with
     * an entry under admin.request_board.status_url reads that instead.
     * Admin-only -- the buyer portal has its own neutral labels
     * (buyer.request_list.status) and is never told how a part was sourced.
     */
    public function adminStatusLabel(): string
    {
        $urlKey = 'admin.request_board.status_url.'.$this->status->value;

        if ($this->is_url_sourced && Lang::has($urlKey)) {
            return __($urlKey);
        }

        return __('admin.request_board.status.'.$this->status->value);
    }

    /**
     * CLAUDE.md §6.3's hard payment gate: the vendor purchase may only be
     * confirmed once the request is `paid` AND a payment is genuinely
     * `confirmed`. The request only reaches `paid` once its payment is
     * confirmed, but the gate still checks the payment record itself --
     * defense in depth, never trusting the status column alone. ConfirmOrderToVendorAction enforces this; the
     * admin view reads the same method to disable its button, so the two
     * can never disagree.
     */
    public function paymentGateOpen(): bool
    {
        return $this->status === RequestStatus::Paid
            && $this->payments()->where('status', PaymentStatus::Confirmed)->exists();
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
