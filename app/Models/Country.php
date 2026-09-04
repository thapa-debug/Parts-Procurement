<?php

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed reference data (client revision -- country was previously a
 * free-text field on buyer_profiles). Never hard-deleted: deactivate via
 * is_active instead, so a country already referenced by an existing buyer
 * profile doesn't need special handling when it's retired from the dropdown.
 */
#[Fillable(['name', 'is_active'])]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @param  Builder<Country>  $query
     * @return Builder<Country>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
