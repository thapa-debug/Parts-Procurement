<?php

namespace App\Models;

use Database\Factories\BuyerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'company_name', 'member_code', 'default_destination_country', 'default_yard', 'phone'])]
class BuyerProfile extends Model
{
    /** @use HasFactory<BuyerProfileFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
