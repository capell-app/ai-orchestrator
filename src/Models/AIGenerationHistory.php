<?php

declare(strict_types=1);

namespace Capell\Assistant\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIGenerationHistory extends Model
{
    use HasFactory;

    protected $table = 'ai_generation_histories';

    protected $fillable = [
        'action',
        'model',
        'input',
        'output',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'duration',
        'metadata',
        'page_id',
        'language_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
