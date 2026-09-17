<?php

namespace App\Models;

use Database\Factories\ShippingWeightBracketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One weight bracket in the rule-based shipping model (CLAUDE.md §14 Phase
 * 4: v1 is weight-only -- DHL and size/dimensions are deferred). upper_kg
 * is nullable on exactly one row -- the top/catch-all bracket ("and
 * above", for heavy items like engines) -- see ShippingCalculator for the
 * lookup logic. Admin-configurable via a future Settings UI slice; not
 * yet built.
 */
#[Fillable(['upper_kg', 'fee', 'order'])]
class ShippingWeightBracket extends Model
{
    /** @use HasFactory<ShippingWeightBracketFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'upper_kg' => 'decimal:2',
    ];
}
