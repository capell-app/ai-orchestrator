<?php

declare(strict_types=1);

namespace Capell\AIOrchestrator\Models;

use Capell\AIOrchestrator\Enums\Ai\ManagedProviderCallState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $call_key_digest
 * @property string $authorization_digest
 * @property string $request_digest
 * @property ManagedProviderCallState $state
 * @property string|null $lease_token
 * @property CarbonImmutable|null $lease_expires_at
 * @property array<int, array<string, mixed>> $attempts
 * @property array<string, mixed>|null $terminal_result
 * @property CarbonImmutable|null $terminal_at
 * @property CarbonImmutable $recovery_expires_at
 */
final class ManagedProviderCall extends Model
{
    protected $table = 'ai_managed_provider_calls';

    protected $fillable = ['call_key_digest', 'authorization_digest', 'request_digest', 'state', 'lease_token', 'lease_expires_at', 'attempts', 'terminal_result', 'terminal_at', 'recovery_expires_at'];

    #[Override]
    protected function casts(): array
    {
        return ['state' => ManagedProviderCallState::class, 'lease_expires_at' => 'immutable_datetime', 'attempts' => 'encrypted:array', 'terminal_result' => 'encrypted:array', 'terminal_at' => 'immutable_datetime', 'recovery_expires_at' => 'immutable_datetime'];
    }
}
