<?php

namespace App\Enums;

enum RelationshipStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Ended = 'ended';
}
