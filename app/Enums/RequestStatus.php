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
}
