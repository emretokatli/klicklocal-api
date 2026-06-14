<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrendHashtag extends Model
{
    /** @use HasFactory<\Database\Factories\TrendHashtagFactory> */
    use HasFactory;

    protected $fillable = [
        'tag',
        'category',
        'volume_label',
        'source',
    ];
}
