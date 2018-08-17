<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property int|null $site_id
 * @property string $action
 * @property string|null $model
 * @property string|null $input
 * @property string|null $output
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property float $duration
 * @property bool $failed
 * @property string|null $error_message
 * @property array<array-key, mixed>|null $metadata
 * @property int|string|null $pageable_id
 * @property string|null $pageable_type
 * @property int|null $language_id
 * @property int|null $created_by_user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|AIGenerationHistory whereAction($value)
 * @method static Builder<static>|AIGenerationHistory whereCompletionTokens($value)
 * @method static Builder<static>|AIGenerationHistory whereCreatedAt($value)
 * @method static Builder<static>|AIGenerationHistory whereCreatedByUserId($value)
 * @method static Builder<static>|AIGenerationHistory whereDuration($value)
 * @method static Builder<static>|AIGenerationHistory whereId($value)
 * @method static Builder<static>|AIGenerationHistory whereInput($value)
 * @method static Builder<static>|AIGenerationHistory whereLanguageId($value)
 * @method static Builder<static>|AIGenerationHistory whereMetadata($value)
 * @method static Builder<static>|AIGenerationHistory whereModel($value)
 * @method static Builder<static>|AIGenerationHistory whereOutput($value)
 * @method static Builder<static>|AIGenerationHistory wherePageId($value)
 * @method static Builder<static>|AIGenerationHistory wherePromptTokens($value)
 * @method static Builder<static>|AIGenerationHistory whereSiteId($value)
 * @method static Builder<static>|AIGenerationHistory whereTotalTokens($value)
 * @method static Builder<static>|AIGenerationHistory whereUpdatedAt($value)
 *
 * @mixin Model
 */
class AIGenerationHistory extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'ai_generation_histories';

    protected $fillable = [
        'site_id',
        'action',
        'model',
        'input',
        'output',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'duration',
        'failed',
        'error_message',
        'metadata',
        'pageable_id',
        'pageable_type',
        'language_id',
        'created_by_user_id',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'failed' => 'boolean',
            // Generation content can contain unpublished copy, prompts, or provider diagnostics.
            'input' => 'encrypted',
            'output' => 'encrypted',
            'error_message' => 'encrypted',
            'metadata' => 'encrypted:array',
        ];
    }
}
