<?php

namespace App\Models;

use Database\Factories\CompanyAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One of the company's own receiving locations (office, yard, ...) --
 * where a vendor delivers a purchased part TO. Admin-managed from
 * Settings; never shown to buyers. Freely editable and deletable:
 * ConfirmOrderToVendorAction snapshots the chosen address onto the
 * part_request's receiving_* columns, so a later edit or delete here never
 * changes where an already-confirmed order told the vendor to deliver.
 */
#[Fillable([
    'label', 'recipient_name', 'phone', 'postal_code', 'prefecture',
    'city', 'address_line1', 'address_line2',
])]
class CompanyAddress extends Model
{
    /** @use HasFactory<CompanyAddressFactory> */
    use HasFactory;
}
