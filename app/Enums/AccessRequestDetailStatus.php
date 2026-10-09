<?php

namespace App\Enums;

enum AccessRequestDetailStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Cancelled = 'cancelled';
}
