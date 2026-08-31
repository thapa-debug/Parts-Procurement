<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The request_vendor pivot needs its own Pivot subclass only so
 * invited_at casts to a real Carbon instance -- Eloquent doesn't cast
 * pivot columns by column-name convention the way it does created_at/
 * updated_at on a normal model.
 */
class RequestVendorPivot extends Pivot
{
    protected $table = 'request_vendor';

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'invited_at' => 'datetime',
    ];
}
