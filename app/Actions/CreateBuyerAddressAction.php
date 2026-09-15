<?php

namespace App\Actions;

use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use Illuminate\Support\Facades\DB;

/**
 * Saves one of a buyer's delivery addresses (CLAUDE.md §14 Phase 4 slice
 * 2). A buyer's very first address always becomes their default regardless
 * of what is_default was passed -- there must always be exactly one
 * default once any address exists, for Slice 3 checkout to preselect. Any
 * subsequent address explicitly marked default unsets every other one in
 * the same transaction, so "default" never means more than one row.
 */
class CreateBuyerAddressAction
{
    /**
     * @param  array{recipient_name: string, phone: string, postal_code: string, country_id: int, state?: string|null, city: string, address_line1: string, address_line2?: string|null, is_default?: bool}  $data
     */
    public function execute(BuyerProfile $buyer, array $data): BuyerAddress
    {
        return DB::transaction(function () use ($buyer, $data) {
            $isDefault = ! $buyer->addresses()->exists() || (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $buyer->addresses()->update(['is_default' => false]);
            }

            return BuyerAddress::create([
                'buyer_id' => $buyer->id,
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'],
                'postal_code' => $data['postal_code'],
                'country_id' => $data['country_id'],
                'state' => $data['state'] ?? null,
                'city' => $data['city'],
                'address_line1' => $data['address_line1'],
                'address_line2' => $data['address_line2'] ?? null,
                'is_default' => $isDefault,
            ]);
        });
    }
}
