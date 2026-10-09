<?php

namespace App\Enums;

enum AccessType: string
{
    case NewStaff = 'New Staff';
    case Privilege = 'Privilege';
    case Temporary = 'Temporary';
    case VendorThirdParty = 'Vendor/3rd Party';
}
