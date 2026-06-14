<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrendFormat extends Model
{
    /** @use HasFactory<\Database\Factories\TrendFormatFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'platform',
        'source',
    ];
}
