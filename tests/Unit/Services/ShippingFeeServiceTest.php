<?php

use App\Actions\PresentQuoteAction;
use App\Actions\SelectQuoteAction;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\CheckoutNotAllowedException;
use App\Models\BuyerAddress;
use App\Models\PartRequest;
use App\Models\ShippingWeightBracket;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use App\Services\DhlRateCalculator;
use App\Services\ShippingFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A request with a selected presented quote whose Regular fee is 8,000.
 *
 * @return array{0: PartRequest, 1: BuyerAddress}
 */
function requestWithSelectedQuote(): array
{
    ShippingWeightBracket::query()->delete();
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 8_000, 'order' => 1]);

    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => VendorProfile::factory()->create()->id,
        'cost_price' => 45_000,
        'weight_kg' => 12,
    ]);
    $quote = app(PresentQuoteAction::class)->execute($request, $response);
    app(SelectQuoteAction::class)->execute($request->fresh(), $quote);

    return [$request->fresh(), BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id])];
}

it('Regular is the presented quote\'s snapshotted fee, never a live bracket recalculation', function () {
    [$request, $address] = requestWithSelectedQuote();
    ShippingWeightBracket::query()->update(['fee' => 99_000]);

    expect(app(ShippingFeeService::class)->feeFor($request, ShippingMethod::Standard, $address))->toBe(8_000);
});

it('DHL is the dummy placeholder fee for now', function () {
    [$request, $address] = requestWithSelectedQuote();

    expect(app(ShippingFeeService::class)->feeFor($request, ShippingMethod::Dhl, $address))->toBe(DhlRateCalculator::DUMMY_FEE);
});

it('lists every method\'s fee for the checkout screen', function () {
    [$request, $address] = requestWithSelectedQuote();

    expect(app(ShippingFeeService::class)->feesFor($request, $address))->toBe([
        'standard' => 8_000,
        'dhl' => DhlRateCalculator::DUMMY_FEE,
    ]);
});

it('asks DhlRateCalculator with the selected part and the destination -- what the real DHL API will need', function () {
    [$request, $address] = requestWithSelectedQuote();

    $dhl = Mockery::mock(DhlRateCalculator::class);
    $dhl->shouldReceive('calculate')
        ->once()
        ->withArgs(fn (VendorResponse $response, BuyerAddress $destination) => $response->id === $request->selected_response_id && $destination->is($address))
        ->andReturn(23_456);
    app()->instance(DhlRateCalculator::class, $dhl);

    expect(app(ShippingFeeService::class)->feeFor($request, ShippingMethod::Dhl, $address))->toBe(23_456);
});

it('refuses Regular when there is no selected presented quote to read the fee from', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::Quoted]);
    $address = BuyerAddress::factory()->create(['buyer_id' => $request->buyer_id]);

    expect(fn () => app(ShippingFeeService::class)->feeFor($request, ShippingMethod::Standard, $address))
        ->toThrow(CheckoutNotAllowedException::class);
});
