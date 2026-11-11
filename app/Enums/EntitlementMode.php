<?php

namespace App\Enums;

enum EntitlementMode: string
{
    case Legacy = 'legacy';
    case MapBased = 'map_based';
}
