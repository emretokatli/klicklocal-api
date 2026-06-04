<?php

namespace App\Enums;

enum PostPlatformStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Failed = 'failed';
}
