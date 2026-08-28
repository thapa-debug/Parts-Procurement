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
