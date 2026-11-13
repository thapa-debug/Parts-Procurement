<?php

use App\Enums\RequestStatus;
use App\Livewire\NotificationCenter;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Notifications\UrlQuotePricedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function pricedUrlRequestForNotification(): PartRequest
{
    return PartRequest::factory()
        ->for(BuyerProfile::factory(), 'buyer')
        ->create([
            'status' => RequestStatus::Quoted,
            'part_name' => 'Alternator',
            'reference_url' => 'https://example.com/listing/123',
            'is_url_sourced' => true,
            'cost_price' => 45_000,
            'buyer_price' => 54_000,
        ]);
}

it('emails the buyer the pay-then-ship wording, not the generic vendor-quote wording', function () {
    $request = pricedUrlRequestForNotification();
    $buyerUser = $request->buyer->user;

    $mail = (new UrlQuotePricedNotification($request))->toMail($buyerUser)->toArray();
    $body = implode(' ', [...$mail['introLines'], ...$mail['outroLines']]);

    $params = ['request_code' => $request->request_code, 'part_name' => 'Alternator', 'buyer_price' => '54,000'];

    expect($mail['subject'])->toBe(__('notifications.mail.quote_ready.subject', $params))
        ->and($mail['actionText'])->toBe(__('notifications.mail.quote_ready.action'))
        ->and($body)->toContain(__('notifications.mail.quote_ready.intro', $params))
        ->and($body)->toContain(__('notifications.mail.quote_ready.price', $params))
        ->and($body)->toContain(__('notifications.mail.quote_ready.after_payment'))
        ->and($body)->not->toContain(__('notifications.mail.quote_presented.line', $params));
});

it('never reveals URL sourcing, the cost, or the margin to the buyer (§4)', function () {
    $request = pricedUrlRequestForNotification();
    $buyerUser = $request->buyer->user;
    $notification = new UrlQuotePricedNotification($request);

    $payload = json_encode($notification->toArray($buyerUser)).json_encode($notification->toMail($buyerUser)->toArray());

    expect($payload)->not->toContain('45000')
        ->and($payload)->not->toContain('45,000')
        ->and($payload)->not->toContain('example.com');

    // The buyer-facing wording itself (bell line, email, on-screen help).
    $mail = $notification->toMail($buyerUser);
    $wording = strtolower(implode(' ', [
        __('notifications.types.quote_ready'),
        $mail->subject, ...$mail->introLines, ...$mail->outroLines,
        __('buyer.request_detail.quote_single_help'),
    ]));

    foreach (['url', 'link', 'sourced', 'margin', 'cost', 'vendor', 'supplier'] as $word) {
        expect($wording)->not->toContain($word);
    }
});

it('renders its own line in the buyer\'s notification centre', function () {
    $request = pricedUrlRequestForNotification();
    $buyerUser = $request->buyer->user;

    $buyerUser->notify(new UrlQuotePricedNotification($request));

    Livewire::actingAs($buyerUser)
        ->test(NotificationCenter::class)
        ->assertSee(__('notifications.types.quote_ready', [
            'part_name' => 'Alternator',
            'request_code' => $request->request_code,
            'buyer_price' => '54,000',
        ]));
});
