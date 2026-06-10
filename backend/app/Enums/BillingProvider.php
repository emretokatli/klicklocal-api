<?php

namespace App\Enums;

enum BillingProvider: string
{
    case Manual = 'manual';
    case Stripe = 'stripe';
    case RevenueCat = 'revenuecat';
}
