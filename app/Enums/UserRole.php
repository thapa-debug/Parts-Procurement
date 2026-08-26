<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Buyer = 'buyer';
    case Vendor = 'vendor';
}
