<?php

use App\Livewire\NotificationCenter;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\User;
use App\Notifications\BuyerApprovedNotification;
use App\Notifications\PartRequestSubmittedNotification;
use App\Notifications\QuotePresentedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows an empty state when the user has no notifications', function () {
    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(NotificationCenter::class)
        ->assertSee(__('notifications.center.empty'))
        ->assertDontSee('99+');
});

it('shows the unread count badge and renders the notification\'s text', function () {
    $admin = User::factory()->admin()->create();
    $buyer = BuyerProfile::factory()->create(['company_name' => 'Acme Imports']);
    $request = PartRequest::factory()->for($buyer, 'buyer')->create(['part_name' => 'Alternator']);

    $admin->notify(new PartRequestSubmittedNotification($request));

    Livewire::actingAs($admin)
        ->test(NotificationCenter::class)
        ->assertSee('1') // the unread badge
        ->assertSee(__('notifications.types.part_request_submitted', [
            'buyer_company_name' => 'Acme Imports',
            'part_name' => 'Alternator',
        ]));
});

it('formats the marked-up buyer_price with thousands separators in the rendered text', function () {
    $buyerProfile = BuyerProfile::factory()->create();
    $request = PartRequest::factory()->for($buyerProfile, 'buyer')->create();
    $presentedQuote = PresentedQuote::factory()->for($request, 'partRequest')->create(['buyer_price' => 54_000]);

    $buyerProfile->user->notify(new QuotePresentedNotification($presentedQuote));

    Livewire::actingAs($buyerProfile->user)
        ->test(NotificationCenter::class)
        ->assertSee('54,000');
});

it('marks a notification read and redirects to its url when clicked', function () {
    $admin = User::factory()->admin()->create();
    $admin->notify(new BuyerApprovedNotification);

    $notification = $admin->notifications()->first();

    Livewire::actingAs($admin)
        ->test(NotificationCenter::class)
        ->call('markAsRead', $notification->id)
        ->assertRedirect(route('buyer.requests.index'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks every unread notification read in one action', function () {
    $admin = User::factory()->admin()->create();
    $admin->notify(new BuyerApprovedNotification);
    $admin->notify(new BuyerApprovedNotification);

    expect($admin->unreadNotifications()->count())->toBe(2);

    Livewire::actingAs($admin)
        ->test(NotificationCenter::class)
        ->call('markAllAsRead');

    expect($admin->unreadNotifications()->count())->toBe(0);
});

it('never lets a user mark, or discover, another user\'s notification by guessing its id', function () {
    $owner = User::factory()->admin()->create();
    $owner->notify(new BuyerApprovedNotification);
    $ownersNotification = $owner->notifications()->first();

    $otherUser = User::factory()->admin()->create();

    Livewire::actingAs($otherUser)
        ->test(NotificationCenter::class)
        ->call('markAsRead', $ownersNotification->id);

    expect($ownersNotification->fresh()->read_at)->toBeNull();
});
