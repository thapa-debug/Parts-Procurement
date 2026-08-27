<?php

use App\Actions\ChangePasswordAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('updates the password and clears the must-change flag', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
        'must_change_password' => true,
    ]);

    app(ChangePasswordAction::class)->execute($user, 'new-strong-password1');

    $user->refresh();

    expect(Hash::check('new-strong-password1', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse();
});

it('leaves the flag cleared for a user who already had it cleared', function () {
    $user = User::factory()->create(['must_change_password' => false]);

    app(ChangePasswordAction::class)->execute($user, 'new-strong-password1');

    expect($user->refresh()->must_change_password)->toBeFalse();
});
