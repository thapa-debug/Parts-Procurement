<?php

namespace App\Enums;

/**
 * The part_request lifecycle (CLAUDE.md §5). Transitions are guarded --
 * never set status by hand where a guarded Action should own the
 * transition.
 */
enum RequestStatus: string
{
    case New = 'new';
    case VendorInquiry = 'vendor_inquiry';
    case Quoted = 'quoted';
    case Paid = 'paid';
    case OrderedToVendor = 'ordered_to_vendor';
    case ProcurementFailed = 'procurement_failed';
    case Shipped = 'shipped';
    case Received = 'received';

    /**
     * Single source of truth for status badge color, by MEANING rather than
     * lifecycle position -- every status badge/tab in every portal reads
     * this, never a hardcoded color per view (UX brush-up). Warm colors
     * mean "needs admin action", blue/indigo mean "in flight", teal/green
     * mean "shipped"/"resolved":
     *
     * - New (新規依頼): nothing has happened yet -- orange.
     * - VendorInquiry (業者照会中) / Quoted (見積もり回答済み): progressing on
     *   its own, no admin action pending -- blue.
     * - Paid (発注・検品中) / OrderedToVendor (発注確定): money has moved,
     *   fulfilment is committed -- indigo.
     * - ProcurementFailed: paid, but the vendor can't supply -- routes back
     *   to re-quoting (CLAUDE.md §5), the one genuinely broken state -- red,
     *   distinct from New's routine orange.
     * - Shipped (発送完了): teal.
     * - Received (受取完了): resolved -- green.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::New => 'bg-orange-50 text-orange-700',
            self::VendorInquiry, self::Quoted => 'bg-blue-50 text-blue-700',
            self::Paid, self::OrderedToVendor => 'bg-indigo-50 text-indigo-700',
            self::ProcurementFailed => 'bg-red-50 text-red-700',
            self::Shipped => 'bg-teal-50 text-teal-700',
            self::Received => 'bg-green-50 text-green-700',
        };
    }
}
