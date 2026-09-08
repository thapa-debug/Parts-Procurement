<?php

use App\Actions\PresentQuoteAction;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// No Notification::fake() here on purpose -- this proves the REAL queue
// routing behaves as CLAUDE.md requires, not just that the right classes
// get called. The test suite's own default queue connection is 'sync'
// (phpunit.xml), which would mask a bug where 'mail' isn't actually
// deferred -- everything would just run inline either way -- so this test
// explicitly points the app's default queue connection at 'database' (a
// real, would-need-a-worker connection) to tell the two channels apart.
it('writes the database notification immediately but defers mail to a real queued job', function () {
    config(['queue.default' => 'database']);

    $buyer = BuyerProfile::factory()->create();
    $request = PartRequest::factory()->for($buyer, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);

    app(PresentQuoteAction::class)->execute($request, $response);

    // 'database' channel: routed through the 'sync' connection regardless
    // of the app's own queue.default -- so the row exists right now, with
    // no queue:work process involved.
    expect(DB::table('notifications')->where('notifiable_id', $buyer->user->id)->count())->toBe(1);

    // 'mail' channel: routed through queue.default ('database' here) --
    // so it lands in the jobs table as real, pending queued work, not sent
    // inline within this request.
    expect(DB::table('jobs')->count())->toBe(1);
});
