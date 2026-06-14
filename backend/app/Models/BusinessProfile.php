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
        'website',
        'team_size',
        'monthly_revenue',
        'customer_source',
        'social_media_channels',
        'target_audience',
        'unique_value_proposition',
        'additional_notes',
        'primary_goal',
        'website_analysis',
        'website_analysis_url',
        'website_analyzed_at',
    ];

    protected $casts = [
        'social_media_channels' => 'array',
        'website_analysis' => 'array',
        'website_analyzed_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isComplete(): bool
    {
        return filled($this->business_name)
            && filled($this->business_type);
    }
}
