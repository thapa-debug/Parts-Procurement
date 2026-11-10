<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
    ];

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isBuyer(): bool
    {
        return $this->role === UserRole::Buyer;
    }

    public function isVendor(): bool
    {
        return $this->role === UserRole::Vendor;
    }

    /**
     * HasLocalePreference: Laravel renders every notification sent to this
     * user inside this locale, then restores the app locale. Vendors are
     * Japanese dismantlers, so their email (打診, verification, ...) comes
     * from lang/ja + lang/ja.json; buyers and admins return null and get the
     * app locale (en). The in-app UI is unaffected -- database notifications
     * store raw data and are rendered at view time in the app locale.
     */
    public function preferredLocale(): ?string
    {
        return $this->isVendor() ? 'ja' : null;
    }

    /**
     * Every admin user -- the recipient list for every admin-facing
     * notification (Phase 3 §"admin" recipient = all users with the admin
     * role). Same scope shape as Country::scopeActive/Maker::scopeActive.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', UserRole::Admin);
    }

    /**
     * @return HasOne<VendorProfile, $this>
     */
    public function vendorProfile(): HasOne
    {
        return $this->hasOne(VendorProfile::class);
    }

    /**
     * @return HasOne<BuyerProfile, $this>
     */
    public function buyerProfile(): HasOne
    {
        return $this->hasOne(BuyerProfile::class);
    }
}
