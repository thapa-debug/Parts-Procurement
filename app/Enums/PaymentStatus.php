<?php

namespace App\Enums;

/**
 * payments.status (CLAUDE.md §7/§14 Phase 4 slice 1) -- an enum, never a
 * boolean, so a `refunded` case can be added later (Phase 2-post-launch,
 * CLAUDE.md §6.4) without migration pain. The column itself is a plain
 * string, not a native DB enum, for the same reason: adding a case here
 * needs no schema change, only this file.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
}
