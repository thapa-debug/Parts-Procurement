<?php

namespace App\Models;

use Database\Factories\ResponsePhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    /**
     * A displayable URL for this already-stored photo, regardless of which
     * disk it landed on (CONVENTIONS.md "Local dev without S3" -- `disk` is
     * saved per-row, so old and new photos can differ). The `public` disk
     * has no private-bucket concept, so a plain url() is correct and
     * doesn't need signing; every other disk (production's `s3`, or a
     * local MinIO standing in for it) is CLAUDE.md's private bucket, so it
     * needs a signed, expiring URL instead of a permanent public one.
     */
    public function url(): string
    {
        if ($this->disk === 'public') {
            return Storage::disk('public')->url($this->path);
        }

        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes(30));
    }
}
