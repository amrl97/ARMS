<?php

namespace App\Enums;

enum AccessRequestStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Rejected = 'rejected';
    case PartiallyApproved = 'partially_approved';
    case Approved = 'approved';
    case Cancelled = 'cancelled';
}
