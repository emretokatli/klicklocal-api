<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrendTopic extends Model
{
    /** @use HasFactory<\Database\Factories\TrendTopicFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'score',
        'source',
        'valid_from',
        'valid_until',
        'raw_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'raw_payload' => 'array',
        ];
    }
}
