<?php

use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\PartRequestSubmittedNotification;
use App\Notifications\QuotePresentedNotification;
use App\Notifications\RequestBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

// Vendors are Japanese dismantlers, so their email renders from lang/ja
// (User::preferredLocale()). Buyers and admins stay on the app locale
// (en). No Notification::fake() here on purpose -- the locale switch
// happens inside Laravel's real NotificationSender, so only a real send
// through the 'array' mailer (phpunit.xml) proves the rendered email.

/**
 * @return array{subject: string, html: string}
 */
function lastSentMail(): array
{
    /** @var SentMessage $sent */
    $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->last();
    $email = $sent->getOriginalMessage();

    return ['subject' => $email->getSubject(), 'html' => $email->getHtmlBody()];
}

it('prefers Japanese for vendors and the app locale for buyers and admins', function () {
    expect(User::factory()->vendor()->make()->preferredLocale())->toBe('ja')
        ->and(User::factory()->buyer()->make()->preferredLocale())->toBeNull()
        ->and(User::factory()->admin()->make()->preferredLocale())->toBeNull();
});

it('renders the vendor 打診 email in Japanese while the app locale stays English', function () {
    $buyer = BuyerProfile::factory()->create(['company_name' => 'Secret Buyer Co']);
    $request = PartRequest::factory()->for($buyer, 'buyer')->create(['status' => RequestStatus::VendorInquiry, 'part_name' => 'Front bumper']);
    $vendor = VendorProfile::factory()->create();

    $vendor->user->notify(new RequestBroadcastNotification($request));

    $mail = lastSentMail();

    expect($mail['subject'])->toBe(__('notifications.mail.request_broadcast.subject', ['part_name' => 'Front bumper'], 'ja'))
        ->and($mail['subject'])->toContain('打診')
        ->and($mail['html'])->toContain(e(__('notifications.mail.request_broadcast.action', [], 'ja')))
        ->and($mail['html'])->toContain(e(__('notifications.mail.no_reply_notice', [], 'ja')))
        ->and($mail['html'])->toContain(e(__('Hello!', [], 'ja')))
        ->and($mail['html'])->toContain($request->request_code)
        ->and($mail['html'])->not->toContain('View request')
        ->and($mail['html'])->not->toContain('Hello!')
        // Isolation (CLAUDE.md §4) holds in Japanese exactly as in English.
        ->and($mail['html'])->not->toContain('Secret Buyer Co');

    expect(app()->getLocale())->toBe('en');
});

it('stores the vendor database notification as raw data, untouched by the mail locale', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();

    $vendor->user->notify(new RequestBroadcastNotification($request));

    expect($vendor->user->notifications()->sole()->data)
        ->toBe((new RequestBroadcastNotification($request))->toArray($vendor->user));
});

it('keeps buyer email in English', function () {
    $quote = PresentedQuote::factory()->create();
    $buyerUser = $quote->partRequest->buyer->user;

    $buyerUser->notify(new QuotePresentedNotification($quote));

    $mail = lastSentMail();

    expect($mail['subject'])->toBe(__('notifications.mail.quote_presented.subject', ['request_code' => $quote->partRequest->request_code], 'en'))
        ->and($mail['html'])->toContain('Hello!')
        ->and($mail['html'])->toContain(__('notifications.mail.quote_presented.action', [], 'en'));
});

it('keeps admin email in English', function () {
    $request = PartRequest::factory()->create(['part_name' => 'Front bumper']);
    $admin = User::factory()->admin()->create();

    $admin->notify(new PartRequestSubmittedNotification($request));

    $mail = lastSentMail();

    expect($mail['subject'])->toBe('New request: Front bumper')
        ->and($mail['html'])->toContain('Hello!');
});

it('sends the vendor verification email in Japanese and the buyer one in English', function () {
    $vendor = User::factory()->vendor()->unverified()->create();
    $buyer = User::factory()->buyer()->unverified()->create();

    $vendor->sendEmailVerificationNotification();
    expect(lastSentMail()['subject'])->toBe(__('Verify your email address', [], 'ja'))
        ->and(lastSentMail()['subject'])->not->toBe('Verify your email address');

    $buyer->sendEmailVerificationNotification();
    expect(lastSentMail()['subject'])->toBe('Verify your email address');
});
