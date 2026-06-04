<?php

namespace App\Enums;

enum SocialAccountStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Expired = 'expired';
    case Error = 'error';
}
