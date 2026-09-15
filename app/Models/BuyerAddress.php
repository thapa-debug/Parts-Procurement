<?php

namespace App\Models;

use Database\Factories\BuyerAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a buyer's saved delivery addresses (CLAUDE.md §14 Phase 4 slice
 * 2) -- a buyer may save any number, with exactly one markable is_default
 * once any exist (enforced by the Buyer*Address Actions, not a DB
 * constraint). Freely editable and deletable: PartRequest's shipping_*
 * columns snapshot the chosen address at checkout time (mirroring the
 * pricing snapshot, §6.2, via SnapshotShippingAddressAction), so a later
 * edit or delete here never changes what an already-placed order shows it
 * shipped to.
 */
#[Fillable([
    'buyer_id', 'recipient_name', 'phone', 'postal_code', 'country_id',
    'state', 'city', 'address_line1', 'address_line2', 'is_default',
])]
class BuyerAddress extends Model
{
    /** @use HasFactory<BuyerAddressFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * @return BelongsTo<BuyerProfile, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(BuyerProfile::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
