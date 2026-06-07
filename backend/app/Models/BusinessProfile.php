<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProfile extends Model
{
    protected $fillable = [
        'workspace_id',
        'business_name',
        'business_type',
        'city',
        'description',
        'tone_of_voice',
        'products_services',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isComplete(): bool
    {
        return filled($this->business_name)
            && filled($this->business_type)
            && filled($this->city);
    }
}
