<?php

use App\Http\Requests\SubmitPartRequestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function submitRequestPayload(array $overrides = []): array
{
    return array_merge([
        'part_type' => 'used',
        'maker' => 'Toyota',
        'car_model' => 'Crown',
        'part_name' => 'Right LED headlight',
    ], $overrides);
}

it('requires the mandatory fields', function () {
    $validator = Validator::make([], (new SubmitPartRequestRequest)->rules());

    expect($validator->fails())->toBeTrue();

    foreach (['part_type', 'maker', 'car_model', 'part_name'] as $field) {
        expect($validator->errors()->has($field))->toBeTrue();
    }
});

it('leaves the optional fields optional', function () {
    $validator = Validator::make(submitRequestPayload(), (new SubmitPartRequestRequest)->rules());

    expect($validator->fails())->toBeFalse();
});

it('accepts a maker from the fixed list', function () {
    $validator = Validator::make(
        submitRequestPayload(['maker' => 'Imported / Other']),
        (new SubmitPartRequestRequest)->rules(),
    );

    expect($validator->fails())->toBeFalse();
});

it('rejects a maker outside the fixed list -- never trust the <select> alone', function () {
    $validator = Validator::make(
        submitRequestPayload(['maker' => 'Ferrari']),
        (new SubmitPartRequestRequest)->rules(),
    );

    expect($validator->errors()->has('maker'))->toBeTrue();
});

it('rejects a part_type outside the enum', function () {
    $validator = Validator::make(
        submitRequestPayload(['part_type' => 'refurbished']),
        (new SubmitPartRequestRequest)->rules(),
    );

    expect($validator->errors()->has('part_type'))->toBeTrue();
});

it('rejects a non-URL reference_url', function () {
    $validator = Validator::make(
        submitRequestPayload(['reference_url' => 'not-a-url']),
        (new SubmitPartRequestRequest)->rules(),
    );

    expect($validator->errors()->has('reference_url'))->toBeTrue();
});

it('accepts mfg_date in YYYY/MM format only', function () {
    $validator = Validator::make(
        submitRequestPayload(['mfg_date' => '2005/10']),
        (new SubmitPartRequestRequest)->rules(),
    );

    expect($validator->fails())->toBeFalse();
});

it('rejects mfg_date in any other format', function (string $invalid) {
    $validator = Validator::make(
        submitRequestPayload(['mfg_date' => $invalid]),
        (new SubmitPartRequestRequest)->rules(),
    );

    expect($validator->errors()->has('mfg_date'))->toBeTrue();
})->with([
    'hyphenated' => '2005-10',
    'day included' => '2005/10/01',
    'month first' => '10/2005',
    'month out of range' => '2005/13',
    'two-digit year' => '05/10',
]);

it('authorizes a buyer only, not an admin or vendor', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    foreach ([[$admin, false], [$buyer, true], [$vendor, false]] as [$user, $expected]) {
        $request = new SubmitPartRequestRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBe($expected);
    }
});
