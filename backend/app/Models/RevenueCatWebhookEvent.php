<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueCatWebhookEvent extends Model
{
    protected $table = 'revenuecat_webhook_events';

    protected $fillable = [
        'event_id',
        'type',
    ];
}
