<?php

namespace App\Enums;

/**
 * How a vendor gets a purchased part to the company -- a fixed per-vendor
 * property (vendor_profiles.delivery_method, client-confirmed), not chosen
 * per order. The order-confirm-to-vendor flow (CLAUDE.md §14 Phase 4
 * slice 6) reads it; this enum only establishes the field.
 */
enum DeliveryMethod: string
{
    // Vendor brings the part to the company in person.
    case Carry = 'carry';

    // Vendor ships/delivers it.
    case Deliver = 'deliver';

    // Vendor can do either.
    case Both = 'both';
}
