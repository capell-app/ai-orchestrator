<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Models;

use Capell\AIOrchestrator\Enums\AiGenerationRequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $fingerprint
 * @property AiGenerationRequestStatus $status
 * @property int|null $site_id
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $generated
 * @property array<string, string>|null $failures
 * @property string|null $error_message
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class AiGenerationRequest extends Model
{
    protected $table = 'ai_generation_requests';

    protected $fillable = [
        'fingerprint',
        'status',
        'site_id',
        'payload',
        'generated',
        'failures',
        'error_message',
        'completed_at',
        'expires_at',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'status' => AiGenerationRequestStatus::class,
            'payload' => 'encrypted:array',
            'generated' => 'encrypted:array',
            'failures' => 'encrypted:array',
            'error_message' => 'encrypted',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
