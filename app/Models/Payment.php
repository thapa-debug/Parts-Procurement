<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'part_request_id', 'amount', 'currency', 'status',
    'gateway', 'gateway_reference', 'raw_response', 'paid_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => PaymentStatus::class,
        'raw_response' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PartRequest, $this>
     */
    public function partRequest(): BelongsTo
    {
        return $this->belongsTo(PartRequest::class);
    }
}
