<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    PaymentServiceProvider::class,
    TelescopeServiceProvider::class,
];
