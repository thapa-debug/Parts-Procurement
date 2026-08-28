<?php

namespace App\Enums;

enum PartType: string
{
    case Used = 'used';
    case New = 'new';
    case Both = 'both';
}
