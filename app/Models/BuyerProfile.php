<?php

namespace App\Models;

use Database\Factories\BuyerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'user_id', 'company_name', 'member_code', 'country_id',
    'phone', 'approved_at', 'approved_by',
])]
class BuyerProfile extends Model
{
    /** @use HasFactory<BuyerProfileFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<BuyerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(BuyerAddress::class, 'buyer_id');
    }

    /**
     * The one BuyerAddress the Buyer*Address Actions keep marked default
     * (CLAUDE.md §14 Phase 4 slice 2) -- null only for a buyer with no
     * saved addresses at all.
     *
     * @return HasOne<BuyerAddress, $this>
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(BuyerAddress::class, 'buyer_id')->where('is_default', true);
    }

    /**
     * Internal business ID (e.g. "BYR-000042"), never a form field -- always
     * system-generated, derived from the user's own unique, auto-increment id
     * so it needs no separate uniqueness check or retry loop.
     */
    public static function generateMemberCode(User $user): string
    {
        return 'BYR-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * CLAUDE.md §14: a new gate on top of email verification -- a
     * self-registered buyer can't create requests until an admin approves
     * them. Admin-created buyers are approved at creation (see
     * CreateBuyerAction); only self-registration leaves this null.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['approved_at']);
    }
}
