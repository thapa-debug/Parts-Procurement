<?php

namespace App\Models;

use Database\Factories\MakerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed reference data (client revision -- maker was previously a
 * fixed dropdown list in lang/en/buyer.php). Never hard-deleted: deactivate
 * via is_active instead, so a maker already referenced by an existing part
 * request doesn't need special handling when it's retired from the dropdown.
 */
#[Fillable(['name', 'is_active'])]
class Maker extends Model
{
    /** @use HasFactory<MakerFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @param  Builder<Maker>  $query
     * @return Builder<Maker>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
