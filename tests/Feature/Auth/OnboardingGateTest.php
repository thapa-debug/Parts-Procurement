<?php

use App\Actions\CreateAdminManagedUserAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

// --- unverified account blocked from acting ---------------------------------

it('blocks an unverified user from acting', function () {
    $user = User::factory()->unverified()->create();

    expect(Gate::forUser($user)->allows('act'))->toBeFalse();
});

it('allows a verified user to act', function () {
    // Vendor, not the factory default (buyer) -- a buyer now carries an
    // extra approval condition (see BuyerApprovalGateTest), so this
    // role-agnostic "verified is enough" case needs a role that isn't one.
    $user = User::factory()->vendor()->create();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and(Gate::forUser($user)->allows('act'))->toBeTrue();
});

// --- temp-password forces change --------------------------------------------

it('creates admin-managed accounts with a temporary password that forces a change', function (UserRole $role) {
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Doe', 'jane@example.com', $role);

    $user = $result['user'];
    $temporaryPassword = $result['temporary_password'];

    expect($user->role)->toBe($role)
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(strlen($temporaryPassword))->toBeGreaterThanOrEqual(16)
        ->and(Hash::check($temporaryPassword, $user->password))->toBeTrue()
        ->and($user->password)->not->toBe($temporaryPassword);
})->with([
    'buyer' => [UserRole::Buyer],
    'vendor' => [UserRole::Vendor],
]);

it('does not force a password change for a self-registered buyer', function () {
    $buyer = User::factory()->buyer()->create();

    expect($buyer->must_change_password)->toBeFalse();
});

// --- must-change middleware is un-bypassable, proven through a real login --

it('redirects to the password-change page after a real login on a temporary password', function () {
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Vendor', 'jane@example.com', UserRole::Vendor);

    $this->post('/login', ['email' => 'jane@example.com', 'password' => $result['temporary_password']])
        ->assertRedirect('/');

    $this->get('/')->assertRedirect(route('password.change'));
});

it('reaches an arbitrary route normally once logged in with a password that needs no change', function () {
    Route::get('/__test/arbitrary', fn () => 'reached')->middleware('web');

    $buyer = User::factory()->buyer()->create();

    $this->post('/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertRedirect('/');

    $this->get('/__test/arbitrary')->assertOk()->assertSee('reached');
});

it('does not redirect loop on the password-change page itself after a real login', function () {
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Vendor', 'jane@example.com', UserRole::Vendor);

    $this->post('/login', ['email' => 'jane@example.com', 'password' => $result['temporary_password']])
        ->assertRedirect('/');

    $this->get(route('password.change'))->assertOk();
});

it('rejects login with the wrong password and leaves the user a guest', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'not-the-right-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('leaves guests alone on routes that do not require auth', function () {
    Route::get('/__test/arbitrary-2', fn () => 'reached')->middleware('web');

    $this->get('/__test/arbitrary-2')->assertOk()->assertSee('reached');
});

it('does not redirect away Livewire\'s own shared update endpoint, or the password-change form would never be able to submit', function () {
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Vendor', 'jane@example.com', UserRole::Vendor);

    $this->post('/login', ['email' => 'jane@example.com', 'password' => $result['temporary_password']])
        ->assertRedirect('/');

    // Matches the real route Livewire registers for all component AJAX
    // traffic (see Livewire\Mechanisms\HandleRequests\HandleRequests::boot).
    Route::post('/__test/livewire-update', fn () => 'reached')
        ->middleware('web')
        ->name('default.livewire.update');

    $this->post('/__test/livewire-update')->assertOk()->assertSee('reached');
});

it('does not redirect Livewire\'s file-upload endpoint, or an upload 500s trying to json_decode the redirect body', function () {
    // Regression test: a redirected livewire/upload-file response isn't
    // valid JSON, so Livewire's own client-side error handler crashes on
    // `json_decode($body, true)['errors']` (WithFileUploads.php) the next
    // time it talks to the server -- a real 500, not just a blocked upload.
    // See CONVENTIONS.md.
    $result = app(CreateAdminManagedUserAction::class)->execute('Jane Vendor', 'jane@example.com', UserRole::Vendor);

    $this->post('/login', ['email' => 'jane@example.com', 'password' => $result['temporary_password']])
        ->assertRedirect('/');

    // Livewire resolves its temporary-upload disk to 'tmp-for-tests' under
    // app()->runningUnitTests(), but only registers that fake disk as a
    // side effect of FileUploadConfiguration::storage() -- a method
    // FileUploadController never actually calls. A component-driven
    // upload triggers that registration earlier by other means; hitting
    // the raw endpoint directly (below) does not, so it must be faked here.
    Storage::fake('tmp-for-tests');

    // The real route Livewire registers for temporary file uploads (see
    // Livewire\Features\SupportFileUploads\SupportFileUploads::boot), hit
    // with a genuine signature the same way Livewire's own JS generates one
    // (GenerateSignedUploadUrl::forLocal), not a stand-in route -- this is
    // exactly the request a real browser's file picker sends.
    $signedPath = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), [], false);

    $this->post($signedPath, [
        'files' => [UploadedFile::fake()->image('bumper.jpg')],
    ])->assertOk()->assertJsonStructure(['paths']);
});
