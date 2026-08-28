<?php

namespace App\Enums;

enum ShippingMethod: string
{
    case Dhl = 'dhl';
    case Vehicle = 'vehicle';
    case Container = 'container';
}
