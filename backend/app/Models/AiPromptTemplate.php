<?php

namespace App\Models;

use App\Enums\AiPromptCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPromptTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'category',
        'description',
        'template',
        'variables',
        'is_active',
        'version',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => AiPromptCategory::class,
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
