<?php

namespace App\Models;

use Database\Factories\ResponsePhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vendor_response_id', 'disk', 'path', 'original_name', 'size'])]
class ResponsePhoto extends Model
{
    /** @use HasFactory<ResponsePhotoFactory> */
    use HasFactory;

    /**
     * CLAUDE.md §7 lists no timestamp columns for this table.
     */
    public $timestamps = false;

    public function vendorResponse(): BelongsTo
    {
        return $this->belongsTo(VendorResponse::class);
    }
}
