<?php

namespace App\Enums;

enum UsageType: string
{
    case Ai = 'ai';
    case SocialApi = 'social_api';
    case QueueJob = 'queue_job';
    case Storage = 'storage';
}
