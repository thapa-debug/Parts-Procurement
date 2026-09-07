<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Max response photos
    |--------------------------------------------------------------------------
    |
    | Maximum number of photos a vendor can attach to a single quote response
    | (App\Livewire\Vendor\RequestResponse). Configurable rather than a
    | hardcoded magic number so it's easy to raise or lower later.
    */
    'max_response_photos' => env('VENDOR_MAX_RESPONSE_PHOTOS', 10),

];
