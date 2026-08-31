<?php

namespace App\Enums;

enum LeadTime: string
{
    case Within2Days = 'within_2_days';
    case Within1Week = 'within_1_week';
    case Within2Weeks = 'within_2_weeks';
    case Undetermined = 'undetermined';
}
