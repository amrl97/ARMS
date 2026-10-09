<?php

namespace App\Enums;

enum RequestPriority: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';
}
