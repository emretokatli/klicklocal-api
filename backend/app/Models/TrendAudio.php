<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrendAudio extends Model
{
    /** @use HasFactory<\Database\Factories\TrendAudioFactory> */
    use HasFactory;

    protected $table = 'trend_audio';

    protected $fillable = [
        'name',
        'platform',
        'external_ref',
        'source',
    ];
}
